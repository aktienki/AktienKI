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
SSH_KEY = '/Users/silviotaubert/.ssh/aktienKI_SSH'
SSH = ['ssh', '-i', SSH_KEY, 'root@217.154.240.14']
RSYNC_SSH = f'ssh -i {SSH_KEY}'
HORIZONS = (5, 10, 15, 20)
LOG = Path('/Users/silviotaubert/AktienKI/.macbook-training/dax-full-pipeline.log')
BLOCKS_BASE = Path('/Users/silviotaubert/AktienKI/.macbook-training/dax-full-blocks')
FINALIZED_RETRY_DAYS = 14
SUCCESS_COOLDOWN_SECONDS = 14 * 24 * 60 * 60


def slot_id() -> str:
    for value in sys.argv:
        if value.startswith('--slot='):
            return value.split('=', 1)[1]
    return '1'


SLOT = slot_id()
WORKER = 'macbook' if SLOT == '3' else 'workstation'


def log(message: str) -> None:
    line = f'{datetime.now().astimezone().isoformat()} slot={SLOT} {message}'
    print(line, flush=True)
    LOG.parent.mkdir(parents=True, exist_ok=True)
    with LOG.open('a', encoding='utf-8') as handle:
        handle.write(line + '\n')


def run(command: list[str], stage: str, symbol: str, cwd: Path = PROJECT) -> None:
    log(f'{stage}_START symbol={symbol}')
    env = os.environ.copy()
    cores = max(2, ((os.cpu_count() or 4) - 2) // 2) if SLOT in {'1', '2'} else max(1, (os.cpu_count() or 4) - 2)
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
              ,NOT EXISTS (
                SELECT 1 FROM stock_individual_thresholds threshold
                WHERE threshold.instrument_id=i.id AND threshold.horizon_days=20
                  AND threshold.algorithm_version='historical-action-v5-per-stock-before-context-filters'
                  AND threshold.status IN ('quality_active','solid_active','basic_documented','unqualified_documented')
              ) AS needs_context_evaluation
            FROM instruments i
            WHERE i.deleted_at IS NULL AND lower(i.type)='stock'
              AND COALESCE(i.meta->>'training_worker', '') IN ('', 'workstation', 'macbook')
              AND (COALESCE(i.meta->>'training_claim_slot', '') IN ('', %s)
                   OR COALESCE(i.meta->>'training_claimed_at', '') = ''
                   OR (i.meta->>'training_claimed_at')::timestamptz < NOW() - INTERVAL '12 hours')
              AND EXISTS (
                SELECT 1 FROM index_memberships membership
                JOIN market_indices market_index ON market_index.id=membership.market_index_id
                WHERE membership.instrument_id=i.id AND membership.removed_at IS NULL
                  AND market_index.symbol='^GDAXI'
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
                ) OR NOT EXISTS (
                  SELECT 1 FROM stock_individual_thresholds threshold
                  WHERE threshold.instrument_id=i.id AND threshold.horizon_days=20
                    AND threshold.algorithm_version='historical-action-v5-per-stock-before-context-filters'
                    AND threshold.status IN ('quality_active','solid_active','basic_documented','unqualified_documented')
                ))
            ORDER BY i.is_active ASC,
                     (COALESCE(i.meta->>'training_worker', '') = %s) DESC,
                     i.market_cap DESC NULLS LAST, i.symbol
            LIMIT 2000 FOR UPDATE SKIP LOCKED
            """,
            (SLOT, WORKER),
        )
        selected = next((dict(row) for row in rows if str(row['symbol']) not in blocked), None)
        if selected is not None and selected.get('training_worker') != WORKER:
            database.execute(
                """UPDATE instruments
                   SET meta=jsonb_set(COALESCE(meta::jsonb,'{}'::jsonb),
                                      '{training_worker}', to_jsonb(%s::text), true)::json,
                       updated_at=NOW()
                   WHERE id=%s""",
                (WORKER, selected['id']),
            )
            database.commit()
            log(f'QUEUE_CLAIMED symbol={selected["symbol"]} worker={WORKER}')
        if selected is not None:
            database.execute(
                """UPDATE instruments
                   SET meta=jsonb_set(
                         jsonb_set(COALESCE(meta::jsonb,'{}'::jsonb),
                                   '{training_claim_slot}', to_jsonb(%s::text), true),
                         '{training_claimed_at}', to_jsonb(NOW()::text), true)::json,
                       updated_at=NOW()
                   WHERE id=%s""",
                (SLOT, selected['id']),
            )
            database.commit()
            log(f'QUEUE_RESERVED symbol={selected["symbol"]}')
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


def maximum_walk_forward_years(instrument_id: int, horizon: int) -> float:
    database = Database()
    try:
        row = database.fetch_one(
            """SELECT MAX((r.test_end-r.test_start)/365.25) AS years
               FROM walk_forward_backtest_runs r
               JOIN walk_forward_backtest_trades t ON t.run_id=r.id
               WHERE r.status='completed' AND r.horizon_days=%s AND t.instrument_id=%s""",
            (horizon, instrument_id),
        )
        return float(row['years'] or 0) if row else 0.0
    finally:
        database.close()


def calibration_event_count(instrument_id: int, horizon: int) -> int:
    database = Database()
    try:
        row = database.fetch_one(
            """SELECT calibration_event_count
               FROM stock_individual_thresholds
               WHERE instrument_id=%s AND horizon_days=%s
                 AND algorithm_version='historical-action-v5-per-stock-before-context-filters'
               ORDER BY calculated_at DESC,id DESC LIMIT 1""",
            (instrument_id, horizon),
        )
        return int(row['calibration_event_count'] or 0) if row else 0
    finally:
        database.close()


def latest_forecast_runs(instrument_id: int) -> dict[int, int]:
    database = Database()
    try:
        rows = database.fetch_all(
            """SELECT DISTINCT ON (r.horizon_days) r.horizon_days,r.id
               FROM walk_forward_backtest_runs r
               JOIN walk_forward_horizon_forecasts f ON f.run_id=r.id AND f.instrument_id=%s
               WHERE r.status='completed' AND r.horizon_days IN (5,10,15,20)
               ORDER BY r.horizon_days,(r.test_end-r.test_start) DESC,r.finished_at DESC,r.id DESC""",
            (instrument_id,),
        )
        return {int(row['horizon_days']): int(row['id']) for row in rows}
    finally:
        database.close()


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
                   AND algorithm_version='historical-action-v5-per-stock-before-context-filters'
                 ORDER BY calculated_at DESC, id DESC LIMIT 1
               ) threshold ON TRUE
               WHERE i.id=%s""",
            (instrument_id,),
        )
        return dict(row) if row else {'is_active': False, 'validation_passed': False, 'threshold_status': 'missing'}
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
        run(['scp', '-i', SSH_KEY, str(payload), 'root@217.154.240.14:/tmp/aktienki-symbol-records.json'], 'REGISTRY_PAYLOAD_UPLOAD', symbol)
        remote = (
            "python3 /home/aktienki/AktienKI/python-engine-v2/scripts/merge_symbol_registry.py "
            f"--symbol {shlex.quote(symbol)} --records /tmp/aktienki-symbol-records.json "
            "--registry /var/lib/aktienki/models/production/registry/registry.json"
        )
        run([*SSH, f"flock /tmp/aktienki-registry.lock -c {shlex.quote(remote)}"], 'REGISTRY_MERGE', symbol)


def upload_phase_filter(symbol: str) -> None:
    safe_symbol = symbol.replace('^', '').replace('/', '_')
    artifact = PHASE_FILTER_DIR / f'{safe_symbol}_three_phase_20t.npz'
    if not artifact.is_file():
        raise RuntimeError(f'Missing phase-filter artifact: {artifact}')
    run([
        'scp', '-i', SSH_KEY, str(artifact),
        'root@217.154.240.14:/var/lib/aktienki/models/production/phase_filters/',
    ], 'PHASE_FILTER_UPLOAD', symbol)


def activate_on_server(symbol: str) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan stocks:activate-completed-training && '
        "php tmp/macbook_queue_status.php | head -22"
    )
    run([*SSH, remote], 'SERVER_ACTIVATION_COUNT', symbol)


def calibrate_on_server(symbol: str, instrument_id: int, horizon: int) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan thresholds:calibrate-index '
        f"'^GDAXI' --horizon={int(horizon)} --instrument={int(instrument_id)} --recalibrate "
        '--validation-years=3 --minimum-calibration-events=10 '
        '--target-calibration-events=20 --minimum-validation-events=10'
    )
    run([*SSH, remote], 'SERVER_INDIVIDUAL_THRESHOLD_CALIBRATION', symbol)


def adaptive_calibration(symbol: str, instrument_id: int, horizon: int) -> None:
    for years in (6, 8, 10):
        if maximum_walk_forward_years(instrument_id, horizon) + 0.1 < years:
            run([str(PYTHON), '-m', 'app.cli.backtest_walk_forward_heatmap',
                 '--years', str(years), '--history-years', '30', '--horizon', str(horizon),
                 '--buy-threshold', '0.01', '--transaction-cost', '0.005',
                 '--position-side', 'long', '--symbols', symbol], f'ADAPTIVE_WALK_FORWARD_{horizon}D_{years}Y', symbol)
        calibrate_on_server(symbol, instrument_id, horizon)
        events = calibration_event_count(instrument_id, horizon)
        role = 'primary' if horizon == 20 else 'confirmation_only'
        log(f'CALIBRATION_EVIDENCE symbol={symbol} horizon={horizon} role={role} years={years} independent_events={events} target=20 minimum=10')
        if events >= 20:
            return
    log(f'CALIBRATION_MAX_HISTORY_REACHED symbol={symbol} horizon={horizon} independent_events={calibration_event_count(instrument_id, horizon)} continuation=true')


def backfill_noise_on_server(symbol: str, instrument_id: int) -> None:
    runs = latest_forecast_runs(instrument_id)
    if set(runs) != {5, 10, 15, 20}:
        log(f'NOISE_HISTORY_UNAVAILABLE symbol={symbol} runs={runs} non_blocking=true')
        return
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan predictions:backfill-noise '
        f'{shlex.quote(symbol)} --run5={runs[5]} --run10={runs[10]} '
        f'--run15={runs[15]} --run20={runs[20]}'
    )
    run([*SSH, remote], 'SERVER_NOISE_BACKFILL', symbol)


def evaluate_context_filters_on_server(symbol: str, instrument_id: int) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan thresholds:evaluate-context-filters '
        f'{int(instrument_id)} --index=^GDAXI --minimum-context-probability=0.45 --minimum-events=10'
    )
    run([*SSH, remote], 'SERVER_CONTEXT_NO_HARM_EVALUATION', symbol)


def postprocess_on_server(symbol: str) -> None:
    commands = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan predictions:apply-horizon-fusion && '
        'sudo -u aktienki php artisan scores:recalculate && '
        'sudo -u aktienki php artisan stocks:classify-risk && '
        "php tmp/macbook_queue_status.php | head -22"
    )
    remote = f"flock /tmp/aktienki-postprocess.lock -c {shlex.quote(commands)}"
    run([*SSH, remote], 'SERVER_FILTERS_AND_COUNT', symbol)


def process(stock: dict) -> bool:
    symbol = str(stock['symbol'])
    missing_models = [int(value) for value in stock.get('missing_models') or []]
    missing_walk_forward = [int(value) for value in stock.get('missing_walk_forward') or []]
    missing_phase_filter = bool(stock.get('missing_phase_filter'))
    needs_context_evaluation = bool(stock.get('needs_context_evaluation'))
    log(f'PIPELINE_START symbol={symbol} missing_models={missing_models} missing_walk_forward={missing_walk_forward} missing_phase_filter={missing_phase_filter}')
    if missing_models:
        run([str(ENGINE), 'train-predict', '--symbol', symbol, '--benchmark', 'auto', '--timeframe', '1d', '--horizons', *[str(horizon) for horizon in sorted(missing_models)], '--training-only', '--minimum-historical-hit-rate', '0.55', '--minimum-profit-factor', '1.3', '--minimum-validation-trades', '15', '--maximum-drawdown', '0.40', '--position-side', 'long'], 'TRAIN_MODELS', symbol)
        missing_walk_forward = sorted(set(missing_walk_forward).union(missing_models))
        upload_models(symbol)
    for horizon in [value for value in missing_walk_forward if value != 20]:
        run([str(PYTHON), '-m', 'app.cli.backtest_walk_forward_heatmap', '--years', '3', '--history-years', '30', '--horizon', str(horizon), '--buy-threshold', '0.01', '--transaction-cost', '0.005', '--position-side', 'long', '--symbols', symbol], f'WALK_FORWARD_{horizon}D', symbol)
        if not walk_forward_complete(int(stock['id']), horizon):
            log(f'WALK_FORWARD_{horizon}D_NO_TRADES symbol={symbol} non_blocking=true')
    for horizon in HORIZONS:
        adaptive_calibration(symbol, int(stock['id']), horizon)
    if missing_phase_filter:
        run([
            str(PYTHON), 'scripts/test_single_stock_market_regimes.py',
            '--symbol', symbol, '--benchmark', 'auto', '--years', '10',
            '--regime-scheme', 'three', '--fold-epochs', '12',
            '--minimum-training-years', '5', '--minimum-regime-samples', '150',
            '--production-filter', '--minimum-filter-accuracy', '0.50',
        ], 'TRAIN_PHASE_FILTER', symbol)
        upload_phase_filter(symbol)
    backfill_noise_on_server(symbol, int(stock['id']))
    evaluate_context_filters_on_server(symbol, int(stock['id']))
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
        values = json.loads(Path(f'{BLOCKS_BASE}-slot{SLOT}.json').read_text(encoding='utf-8'))
        return {str(symbol): float(deadline) for symbol, deadline in values.items()}
    except (FileNotFoundError, ValueError, TypeError):
        return {}


def save_blocks(blocked_until: dict[str, float]) -> None:
    Path(f'{BLOCKS_BASE}-slot{SLOT}.json').write_text(json.dumps(blocked_until, sort_keys=True), encoding='utf-8')


def release_claim(instrument_id: int) -> None:
    database = Database()
    try:
        database.execute(
            """UPDATE instruments
               SET meta=(COALESCE(meta::jsonb,'{}'::jsonb)-'training_claim_slot'-'training_claimed_at')::json,
                   updated_at=NOW()
               WHERE id=%s AND COALESCE(meta->>'training_claim_slot','')=%s""",
            (instrument_id, SLOT),
        )
        database.commit()
    finally:
        database.close()


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
                 AND instrument.meta->>'training_worker'=%s""",
            (WORKER,),
        )
        database.commit()
        if recovered:
            log(f'ORPHANED_TRAINING_RUNS_RECOVERED count={recovered}')
    finally:
        database.close()


def preflight() -> None:
    required_paths = [PROJECT, ENGINE, PYTHON, REGISTRY / 'registry.json', SSH_KEY]
    missing = [str(path) for path in required_paths if not Path(path).exists()]
    if missing:
        raise RuntimeError(f'PREFLIGHT missing paths: {missing}')
    database = Database()
    try:
        row = database.fetch_one('SELECT 1 AS ok')
        if not row or int(row['ok']) != 1:
            raise RuntimeError('PREFLIGHT database query failed')
    finally:
        database.close()
    result = subprocess.run([*SSH, 'cd /home/aktienki/AktienKI/laravel && php artisan --version'],
                            cwd=PROJECT, check=False, timeout=30, capture_output=True, text=True)
    if result.returncode != 0:
        raise RuntimeError(f'PREFLIGHT server SSH/Laravel failed: {result.stderr.strip()}')
    log(f'PREFLIGHT_OK engine={ENGINE} database=ok server={result.stdout.strip()}')


def main() -> int:
    log('DAX_FULL_PIPELINE_START parallel=2_workstation_plus_1_macbook horizons=5,10,15,20')
    preflight()
    if '--preflight' in sys.argv:
        return 0
    recover_orphaned_training_runs()
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
            release_claim(int(stock['id']))
            log(
                f'QUEUE_FINALIZED symbol={stock["symbol"]} '
                f'prediction_complete={str(prediction_complete).lower()} '
                f'cooldown={SUCCESS_COOLDOWN_SECONDS}s'
            )
            if '--once' in sys.argv:
                return 0
        except Exception as exception:
            # A technical failure must not silently remove a stock from the
            # production pipeline. Keep the failed run in the database/log and
            # retry the same incomplete stock after a short backoff.
            blocked_until.pop(str(stock['symbol']), None)
            save_blocks(blocked_until)
            log(f'PIPELINE_FAILED_DOCUMENTED symbol={stock["symbol"]} error={exception!r} retry=60s')
            time.sleep(60)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
