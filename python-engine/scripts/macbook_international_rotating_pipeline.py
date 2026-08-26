from __future__ import annotations

import json
import os
import re
import shlex
import subprocess
import sys
import tempfile
import time
from datetime import datetime
from pathlib import Path

PROJECT = Path('/Users/silviotaubert/Downloads/python-engine')
sys.path.insert(0, str(PROJECT))

from app.database import Database


ENGINE = PROJECT / '.venv/bin/aktienki-engine'
PYTHON = PROJECT / '.venv/bin/python'
REGISTRY = PROJECT / 'models_storage_current/registry'
PHASE_FILTER_DIR = PROJECT / 'models_storage_current/phase_filters'
SSH = ['ssh', '-i', '/Users/silviotaubert/.ssh/aktienKI_SSH', 'root@217.154.240.14']
RSYNC_SSH = 'ssh -i /Users/silviotaubert/.ssh/aktienKI_SSH'
HORIZONS = (5, 10, 15, 20)
LOG = Path('/Users/silviotaubert/AktienKI/.macbook-training/international-rotating-pipeline.log')
BLOCKS = Path('/Users/silviotaubert/AktienKI/.macbook-training/international-rotating-blocks.json')
FINALIZED_RETRY_DAYS = 14
SUCCESS_COOLDOWN_SECONDS = 14 * 24 * 60 * 60
FAILURE_COOLDOWN_SECONDS = 6 * 60 * 60


def log(message: str) -> None:
    line = f'{datetime.now().astimezone().isoformat()} {message}'
    print(line, flush=True)
    LOG.parent.mkdir(parents=True, exist_ok=True)
    with LOG.open('a', encoding='utf-8') as handle:
        handle.write(line + '\n')


def run(command: list[str], stage: str, symbol: str, cwd: Path = PROJECT) -> None:
    log(f'{stage}_START symbol={symbol}')
    env = os.environ.copy()
    cores = max(1, (os.cpu_count() or 4) - 2)
    env.update({'TRAINING_YEARS': '30', 'OMP_NUM_THREADS': str(cores), 'OPENBLAS_NUM_THREADS': str(cores), 'MKL_NUM_THREADS': str(cores)})
    timeout = 7200 if stage == 'TRAIN_MODELS' else 2400
    try:
        result = subprocess.run(command, cwd=cwd, env=env, check=False, timeout=timeout)
    except subprocess.TimeoutExpired as exception:
        raise RuntimeError(f'{stage} timed out after {timeout}s') from exception
    if result.returncode != 0:
        raise RuntimeError(f'{stage} failed with exit code {result.returncode}')
    log(f'{stage}_DONE symbol={symbol}')


def next_stock(blocked: set[str]) -> dict | None:
    database = Database()
    try:
        rows = database.fetch_all(
            """
            SELECT i.id, i.symbol, COALESCE(i.meta->>'training_worker', '') AS training_worker,
              COALESCE((
                SELECT home_index.symbol
                FROM index_memberships home_membership
                JOIN market_indices home_index ON home_index.id=home_membership.market_index_id
                WHERE home_membership.instrument_id=i.id
                  AND home_membership.removed_at IS NULL
                ORDER BY home_membership.weight DESC NULLS LAST, home_index.id
                LIMIT 1
              ), '^GSPC') AS home_index_symbol,
              ARRAY(SELECT h FROM unnest(ARRAY[5,10,15,20]) h WHERE NOT EXISTS (
                SELECT 1 FROM trained_models m WHERE m.instrument_id=i.id AND m.deleted_at IS NULL
                  AND m.status='active'
                  AND m.feature_set_version='triple_daily_macro_v1'
                  AND m.prediction_horizon_minutes=h*1440)) missing_models,
              ARRAY(SELECT h FROM unnest(ARRAY[5,10,15,20]) h WHERE NOT EXISTS (
                SELECT 1 FROM walk_forward_backtest_scores s JOIN walk_forward_backtest_runs r ON r.id=s.run_id
                WHERE s.instrument_id=i.id AND s.horizon_days=h AND r.status='completed' AND s.trade_count>0)) missing_walk_forward,
              NOT EXISTS (
                SELECT 1 FROM market_context_predictions context
                WHERE context.scope_type='stock_phase20' AND context.scope_key=i.id::text
                  AND context.meta->>'source'='pytorch_stock_three_phase_gru_20t'
              ) AS missing_phase_filter
            FROM instruments i
            WHERE i.deleted_at IS NULL AND lower(i.type)='stock'
              AND COALESCE(i.meta->>'training_worker', '') IN ('', 'macbook', 'workstation', 'workstation-2')
              AND COALESCE(NULLIF(BTRIM(i.country), ''), 'unknown') NOT IN ('DE', 'DEU', 'Deutschland', 'Germany')
              AND NOT EXISTS (
                SELECT 1 FROM index_memberships dax_membership
                JOIN market_indices dax_index ON dax_index.id=dax_membership.market_index_id
                WHERE dax_membership.instrument_id=i.id
                  AND dax_membership.removed_at IS NULL
                  AND dax_index.symbol='^GDAXI'
              )
              AND EXISTS (
                SELECT 1 FROM price_bars b
                WHERE b.instrument_id=i.id AND b.interval='1d'
                GROUP BY b.instrument_id
                HAVING COUNT(*) >= 80
                   AND MAX(b.bar_time) - MIN(b.bar_time) >= INTERVAL '1000 days'
              )
              AND (EXISTS (SELECT 1 FROM unnest(ARRAY[5,10,15,20]) h WHERE NOT EXISTS (
                SELECT 1 FROM trained_models m WHERE m.instrument_id=i.id AND m.deleted_at IS NULL
                  AND m.status='active'
                  AND m.feature_set_version='triple_daily_macro_v1'
                  AND m.prediction_horizon_minutes=h*1440))
                OR EXISTS (SELECT 1 FROM unnest(ARRAY[5,10,15,20]) h WHERE NOT EXISTS (
                SELECT 1 FROM walk_forward_backtest_scores s JOIN walk_forward_backtest_runs r ON r.id=s.run_id
                WHERE s.instrument_id=i.id AND s.horizon_days=h AND r.status='completed' AND s.trade_count>0))
                OR NOT EXISTS (
                  SELECT 1 FROM market_context_predictions context
                  WHERE context.scope_type='stock_phase20' AND context.scope_key=i.id::text
                    AND context.meta->>'source'='pytorch_stock_three_phase_gru_20t'
                ))
            ORDER BY (
                       SELECT COUNT(DISTINCT existing_model.prediction_horizon_minutes)
                       FROM trained_models existing_model
                       WHERE existing_model.instrument_id=i.id
                         AND existing_model.deleted_at IS NULL
                         AND existing_model.status='active'
                         AND existing_model.feature_set_version='triple_daily_macro_v1'
                         AND existing_model.prediction_horizon_minutes IN (7200,14400,21600,28800)
                     ) DESC,
                     (
                       SELECT COUNT(DISTINCT existing_score.horizon_days)
                       FROM walk_forward_backtest_scores existing_score
                       JOIN walk_forward_backtest_runs existing_run ON existing_run.id=existing_score.run_id
                       WHERE existing_score.instrument_id=i.id
                         AND existing_run.status='completed'
                         AND existing_score.trade_count>0
                         AND existing_score.horizon_days IN (5,10,15,20)
                     ) DESC,
                     CASE WHEN EXISTS (
                       SELECT 1 FROM market_context_predictions existing_phase
                       WHERE existing_phase.scope_type='stock_phase20'
                         AND existing_phase.scope_key=i.id::text
                         AND existing_phase.meta->>'source'='pytorch_stock_three_phase_gru_20t'
                     ) THEN 0 ELSE 1 END,
                     (
                       SELECT MAX(country_run.finished_at)
                       FROM training_runs country_run
                       JOIN instruments country_instrument ON country_instrument.id=country_run.instrument_id
                       WHERE country_instrument.country IS NOT DISTINCT FROM i.country
                         AND country_instrument.meta->>'training_worker'='macbook'
                         AND country_run.status IN ('completed', 'failed')
                     ) ASC NULLS FIRST,
                     i.is_active ASC,
                     (COALESCE(i.meta->>'training_worker', '') = 'macbook') DESC,
                     i.market_cap DESC NULLS LAST, i.symbol
            LIMIT 2000 FOR UPDATE SKIP LOCKED
            """
        )
        selected = next((dict(row) for row in rows if str(row['symbol']) not in blocked), None)
        if selected is not None and selected.get('training_worker') != 'macbook':
            database.execute(
                """UPDATE instruments
                   SET meta=jsonb_set(COALESCE(meta::jsonb,'{}'::jsonb),
                                      '{training_worker}', '\"macbook\"'::jsonb, true)::json,
                       updated_at=NOW()
                   WHERE id=%s""",
                (selected['id'],),
            )
            database.commit()
            log(f'QUEUE_CLAIMED symbol={selected["symbol"]} worker=macbook')
        return selected
    finally:
        database.close()


def walk_forward_complete(instrument_id: int, horizon: int) -> bool:
    database = Database()
    try:
        row = database.fetch_one(
            """SELECT 1 AS ok FROM walk_forward_backtest_scores s
               JOIN walk_forward_backtest_runs r ON r.id=s.run_id
               WHERE s.instrument_id=%s AND s.horizon_days=%s
                 AND r.status='completed' AND s.trade_count>0 LIMIT 1""",
            (instrument_id, horizon),
        )
        return bool(row)
    finally:
        database.close()


def active_horizons(instrument_id: int) -> set[int]:
    database = Database()
    try:
        rows = database.fetch_all(
            """SELECT DISTINCT prediction_horizon_minutes / 1440 AS horizon
               FROM trained_models
               WHERE instrument_id=%s AND deleted_at IS NULL AND status='active'
                 AND feature_set_version='triple_daily_macro_v1'
                 AND prediction_horizon_minutes IN (7200,14400,21600,28800)""",
            (instrument_id,),
        )
        return {int(row['horizon']) for row in rows}
    finally:
        database.close()


def upload_models(symbol: str) -> None:
    records = json.loads((REGISTRY / 'registry.json').read_text(encoding='utf-8'))
    selected = [
        record for record in records
        if str(
            record.get('metadata', {}).get('symbol')
            or record.get('metadata', {}).get('dataset_metadata', {}).get('symbol', '')
        ).upper() == symbol.upper()
    ]
    if not selected:
        raise RuntimeError(f'No registry records found for {symbol}')
    paths = sorted({str(record['artifact']['relative_path']) for record in selected})
    missing = [path for path in paths if not (REGISTRY / 'artifacts' / path).is_file()]
    if missing:
        raise RuntimeError(f'Missing local artifacts for {symbol}: {missing[:3]}')
    with tempfile.TemporaryDirectory(prefix='aktienki-upload-') as directory:
        payload = Path(directory) / 'records.json'
        payload.write_text(json.dumps(selected, ensure_ascii=False), encoding='utf-8')
        run(['rsync', '-azR', '-e', RSYNC_SSH, *paths, 'root@217.154.240.14:/var/lib/aktienki/models/production/registry/artifacts/'], 'MODEL_ARTIFACT_UPLOAD', symbol, REGISTRY / 'artifacts')
        run(['scp', '-i', '/Users/silviotaubert/.ssh/aktienKI_SSH', str(payload), 'root@217.154.240.14:/tmp/aktienki-symbol-records.json'], 'REGISTRY_PAYLOAD_UPLOAD', symbol)
        remote = (
            "python3 /home/aktienki/AktienKI/python-engine-v2/scripts/merge_symbol_registry.py "
            f"--symbol {shlex.quote(symbol)} --records /tmp/aktienki-symbol-records.json "
            "--registry /var/lib/aktienki/models/production/registry/registry.json"
        )
        run([*SSH, remote], 'REGISTRY_MERGE', symbol)


def upload_phase_filter(symbol: str) -> None:
    safe_symbol = symbol.replace('^', '').replace('/', '_')
    artifact = PHASE_FILTER_DIR / f'{safe_symbol}_three_phase_20t.npz'
    if not artifact.is_file():
        raise RuntimeError(f'Missing phase-filter artifact: {artifact}')
    run([
        'scp', '-i', '/Users/silviotaubert/.ssh/aktienKI_SSH', str(artifact),
        'root@217.154.240.14:/var/lib/aktienki/models/production/phase_filters/',
    ], 'PHASE_FILTER_UPLOAD', symbol)


def activate_on_server(symbol: str) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan stocks:activate-completed-training && '
        "php tmp/macbook_queue_status.php | head -22"
    )
    run([*SSH, remote], 'SERVER_ACTIVATION_COUNT', symbol)


def calibrate_on_server(symbol: str, instrument_id: int, home_index_symbol: str) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan thresholds:calibrate-index '
        f'{shlex.quote(home_index_symbol)} --horizon=20 --instrument={int(instrument_id)}'
    )
    run([*SSH, remote], 'SERVER_INDIVIDUAL_THRESHOLD_CALIBRATION', symbol)


def production_release_status(instrument_id: int) -> dict:
    database = Database()
    try:
        row = database.fetch_one(
            """SELECT i.is_active,
                      threshold.status AS threshold_status,
                      COALESCE(threshold.validation_passed, FALSE) AS validation_passed
               FROM instruments i
               LEFT JOIN LATERAL (
                 SELECT status, validation_passed
                 FROM stock_individual_thresholds
                 WHERE instrument_id=i.id AND horizon_days=20
                 ORDER BY calculated_at DESC, id DESC LIMIT 1
               ) threshold ON TRUE
               WHERE i.id=%s""",
            (instrument_id,),
        )
        return dict(row) if row else {}
    finally:
        database.close()


def postprocess_on_server(symbol: str) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan predictions:apply-horizon-fusion && '
        'sudo -u aktienki php artisan scores:recalculate && '
        'sudo -u aktienki php artisan stocks:classify-risk && '
        "php tmp/macbook_queue_status.php | head -22"
    )
    run([*SSH, remote], 'SERVER_FILTERS_AND_COUNT', symbol)


def prepare_index_contexts() -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan predictions:index-pytorch60-context --max-members=25'
    )
    run([*SSH, remote], 'INDEX_CONTEXT_PREFLIGHT', 'ALL_INDICES')


def process(stock: dict) -> bool:
    symbol = str(stock['symbol'])
    missing_models = [int(value) for value in stock.get('missing_models') or []]
    missing_walk_forward = [int(value) for value in stock.get('missing_walk_forward') or []]
    missing_phase_filter = bool(stock.get('missing_phase_filter'))
    log(f'PIPELINE_START symbol={symbol} benchmark=auto_home_index missing_models={missing_models} missing_walk_forward={missing_walk_forward} missing_phase_filter={missing_phase_filter}')
    if missing_models:
        run([str(ENGINE), 'train-predict', '--symbol', symbol, '--benchmark', 'auto', '--timeframe', '1d', '--horizons', *[str(horizon) for horizon in sorted(missing_models)], '--training-only', '--minimum-historical-hit-rate', '0.55', '--minimum-profit-factor', '1.3', '--minimum-validation-trades', '15', '--maximum-drawdown', '0.40', '--position-side', 'long'], 'TRAIN_MODELS', symbol)
        missing_walk_forward = sorted(set(missing_walk_forward).union(missing_models))
    for horizon in missing_walk_forward:
        run([str(PYTHON), '-m', 'app.cli.backtest_walk_forward_heatmap', '--years', '3', '--history-years', '30', '--horizon', str(horizon), '--buy-threshold', '0.01', '--transaction-cost', '0.005', '--position-side', 'long', '--symbols', symbol], f'WALK_FORWARD_{horizon}D', symbol)
        if not walk_forward_complete(int(stock['id']), horizon):
            log(f'WALK_FORWARD_{horizon}D_NO_TRADES symbol={symbol} non_blocking=true')
    # Calibrate the stock-specific raw-score threshold before any phase,
    # index, sector or 60T context filter is applied.
    calibrate_on_server(
        symbol,
        int(stock['id']),
        str(stock.get('home_index_symbol') or '^GSPC'),
    )
    if missing_phase_filter:
        run([
            str(PYTHON), 'scripts/test_single_stock_market_regimes.py',
            '--symbol', symbol, '--benchmark', 'auto', '--years', '10',
            '--regime-scheme', 'three', '--fold-epochs', '12',
            '--minimum-training-years', '5', '--minimum-regime-samples', '150',
            '--production-filter', '--minimum-filter-accuracy', '0.50',
        ], 'TRAIN_PHASE_FILTER', symbol)
        upload_phase_filter(symbol)
    # Existing server-side models do not necessarily have a duplicate artifact
    # in the MacBook registry. Upload only models trained in this run.
    if missing_models:
        upload_models(symbol)
    activate_on_server(symbol)
    release = production_release_status(int(stock['id']))
    if not bool(release.get('validation_passed')) or not bool(release.get('is_active')):
        log(
            f'PIPELINE_VALIDATION_DOCUMENTED symbol={symbol} '
            f'threshold_status={release.get("threshold_status")} '
            f'validation_passed={bool(release.get("validation_passed"))} '
            f'is_active={bool(release.get("is_active"))} continuation=true'
        )
    usable_horizons = active_horizons(int(stock['id']))
    minimum_horizons_available = 20 in usable_horizons and len(usable_horizons) >= 2
    if not minimum_horizons_available:
        log(
            f'PIPELINE_QUALITY_GATE_DOCUMENTED symbol={symbol} '
            f'active_horizons={sorted(usable_horizons)} required=20T_plus_one retry_after_days={FINALIZED_RETRY_DAYS}'
        )
    else:
        run([str(ENGINE), 'predict-active', '--ai-type', 'horizon', '--position-side', 'long', '--limit', '4', '--symbols', symbol, '--recalculate', '--no-refresh'], 'FINAL_WORKSTATION_PREDICTION', symbol)
    postprocess_on_server(symbol)
    log(f'PIPELINE_COMPLETE symbol={symbol} validation_passed={bool(release.get("validation_passed"))} is_active={bool(release.get("is_active"))}')
    return minimum_horizons_available


def load_blocks() -> dict[str, float]:
    try:
        values = json.loads(BLOCKS.read_text(encoding='utf-8'))
        return {str(symbol): float(deadline) for symbol, deadline in values.items()}
    except (FileNotFoundError, ValueError, TypeError):
        return {}


def save_blocks(blocked_until: dict[str, float]) -> None:
    BLOCKS.write_text(json.dumps(blocked_until, sort_keys=True), encoding='utf-8')


def recover_orphaned_training_runs() -> None:
    database = Database()
    try:
        recovered = database.execute(
            """UPDATE training_runs run
               SET status='failed',
                   error_message='Vom persistenten MacBook-Runner nach Prozessabbruch bereinigt',
                   finished_at=NOW(), updated_at=NOW()
               FROM instruments instrument
               WHERE run.instrument_id=instrument.id
                 AND run.status='running'
                 AND instrument.meta->>'training_worker'='macbook'"""
        )
        database.commit()
        if recovered:
            log(f'ORPHANED_TRAINING_RUNS_RECOVERED count={recovered}')
    finally:
        database.close()


def main() -> int:
    log('INTERNATIONAL_ROTATING_PIPELINE_START parallel=1 horizons=5,10,15,20 exclude_dax=true')
    recover_orphaned_training_runs()
    prepare_index_contexts()
    blocked_until = load_blocks()
    while True:
        now = time.time()
        blocked_until = {symbol: deadline for symbol, deadline in blocked_until.items() if deadline > now}
        blocked = {symbol for symbol, deadline in blocked_until.items() if deadline > now}
        stock = next_stock(blocked)
        if stock is None:
            log('QUEUE_EMPTY_OR_BLOCKED sleeping=300s')
            time.sleep(300)
            continue
        try:
            prediction_complete = process(stock)
            blocked_until[str(stock['symbol'])] = time.time() + SUCCESS_COOLDOWN_SECONDS
            save_blocks(blocked_until)
            log(
                f'QUEUE_FINALIZED symbol={stock["symbol"]} '
                f'prediction_complete={str(prediction_complete).lower()} '
                f'cooldown={SUCCESS_COOLDOWN_SECONDS}s'
            )
        except Exception as exception:
            blocked_until[str(stock['symbol'])] = time.time() + FAILURE_COOLDOWN_SECONDS
            save_blocks(blocked_until)
            log(
                f'PIPELINE_FAILED_DOCUMENTED symbol={stock["symbol"]} error={exception!r} '
                f'continuation=true cooldown={FAILURE_COOLDOWN_SECONDS}s'
            )
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
