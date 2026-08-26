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
ARIMA_REPORT_DIR = PROJECT / 'reports/pipeline_arima_validation'
SSH = ['ssh', '-i', '/Users/silviotaubert/.ssh/aktienKI_SSH', 'root@217.154.240.14']
RSYNC_SSH = 'ssh -i /Users/silviotaubert/.ssh/aktienKI_SSH'
WORKSTATION = ['ssh', 'aki']
WORKSTATION_PROJECT = Path('/home/akiadmin/projects/ml/AktienKI-Python-Engine')
HORIZONS = (5, 10, 15, 20)
LOG = Path('/Users/silviotaubert/AktienKI/.macbook-training/sequential-pipeline.log')
BLOCKS = Path('/Users/silviotaubert/AktienKI/.macbook-training/sequential-blocks.json')
EMAIL_COUNTER = Path('/Users/silviotaubert/AktienKI/.macbook-training/arima-revalidation-email-count.json')
EMAIL_LIMIT = 3
FINALIZED_RETRY_DAYS = 14
SUCCESS_COOLDOWN_SECONDS = 14 * 24 * 60 * 60


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
        continuation = database.fetch_one(
            """SELECT * FROM python_engine_jobs
               WHERE type='stock_training_continuation' AND status='queued'
               ORDER BY created_at,id FOR UPDATE SKIP LOCKED LIMIT 1"""
        )
        if continuation is not None:
            payload = continuation['payload'] if isinstance(continuation['payload'], dict) else json.loads(continuation['payload'])
            symbol = str(payload['symbol'])
            if symbol not in blocked:
                updated = database.execute(
                    """UPDATE python_engine_jobs SET status='running',progress=5,attempts=attempts+1,
                       locked_by='macbook-finalizer',locked_at=NOW(),heartbeat_at=NOW(),
                       started_at=COALESCE(started_at,NOW()),updated_at=NOW()
                       WHERE id=%s AND status='queued'""",
                    (continuation['id'],),
                )
                database.commit()
                if updated == 1:
                    instrument = database.fetch_one(
                        "SELECT id,symbol,COALESCE(meta->>'training_worker','') training_worker FROM instruments WHERE id=%s",
                        (payload['instrument_id'],),
                    )
                    return dict(instrument) | {
                        'missing_models': [], 'missing_walk_forward': [],
                        'missing_phase_filter': True,
                        'continuation_job_id': int(continuation['id']),
                        'source_worker': str(payload.get('source_worker', 'workstation')),
                        'reason': str(payload.get('reason', '')),
                    }
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
            FROM instruments i
            WHERE i.deleted_at IS NULL AND lower(i.type)='stock'
              AND COALESCE(i.meta->>'training_worker', '') IN ('', 'macbook')
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
            ORDER BY i.is_active ASC,
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


def transfer_workstation_models(symbol: str) -> None:
    code = (
        "import json,pathlib;"
        f"p=pathlib.Path({str(WORKSTATION_PROJECT / 'models_storage/registry/registry.json')!r});"
        "r=json.loads(p.read_text());"
        f"s={symbol.upper()!r};"
        "x=[v for v in r if str(v.get('metadata',{}).get('symbol') or v.get('metadata',{}).get('dataset_metadata',{}).get('symbol','')).upper()==s];"
        "print(json.dumps(x))"
    )
    remote_command = shlex.join(['python3', '-c', code])
    result = subprocess.run([*WORKSTATION, remote_command], text=True, capture_output=True, check=False, timeout=60)
    if result.returncode != 0:
        raise RuntimeError(f'Cannot read workstation registry for {symbol}: {result.stderr[-500:]}')
    selected = json.loads(result.stdout)
    if not selected:
        raise RuntimeError(f'No workstation registry records for {symbol}')
    relative_paths = sorted({str(record['artifact']['relative_path']) for record in selected})
    sources = [f"aki:{WORKSTATION_PROJECT}/models_storage/registry/artifacts/./{path}" for path in relative_paths]
    run(['rsync', '-azR', *sources, str(REGISTRY / 'artifacts') + '/'], 'WORKSTATION_ARTIFACT_TRANSFER', symbol)
    registry_path = REGISTRY / 'registry.json'
    records = json.loads(registry_path.read_text(encoding='utf-8'))
    selected_ids = {str(record.get('id') or record.get('model_id') or record['artifact']['relative_path']) for record in selected}
    records = [record for record in records if str(record.get('id') or record.get('model_id') or record['artifact']['relative_path']) not in selected_ids]
    registry_path.write_text(json.dumps(records + selected, ensure_ascii=False, indent=2), encoding='utf-8')
    log(f'WORKSTATION_ARTIFACT_TRANSFER_DONE symbol={symbol} records={len(selected)}')


def finish_continuation_job(job_id: int | None, status: str, result: dict | None = None, error: str | None = None) -> None:
    if not job_id:
        return
    database = Database()
    try:
        database.execute(
            """UPDATE python_engine_jobs SET status=%s,progress=%s,result=%s::jsonb,error_message=%s,
               heartbeat_at=NOW(),finished_at=CASE WHEN %s IN ('completed','failed') THEN NOW() ELSE finished_at END,
               updated_at=NOW() WHERE id=%s""",
            (status, 100 if status == 'completed' else 0, json.dumps(result or {}), error, status, job_id),
        )
        database.commit()
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


def instrument_is_active(instrument_id: int) -> bool:
    database = Database()
    try:
        row = database.fetch_one('SELECT is_active FROM instruments WHERE id=%s', (instrument_id,))
        return bool(row and row['is_active'])
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


def run_and_document_arima_validation(symbol: str, instrument_id: int) -> dict:
    """Run the mandatory MacBook-only ARIMA comparison before finalization."""
    ARIMA_REPORT_DIR.mkdir(parents=True, exist_ok=True)
    output = ARIMA_REPORT_DIR / f'{symbol}.json'
    run([
        str(PYTHON), 'scripts/test_timeseries_pytorch_meta_20t.py',
        '--symbol', symbol, '--years', '10',
        '--variants', 'A_baseline,D_arima_timeseries',
        '--output', str(output),
    ], 'ARIMA_TIMESERIES_VALIDATION', symbol)

    payload = json.loads(output.read_text(encoding='utf-8'))
    variants = payload.get('variants') or {}
    baseline = (variants.get('A_baseline') or {}).get('account_simulation') or {}
    arima_variant = variants.get('D_arima_timeseries') or {}
    arima = arima_variant.get('account_simulation') or {}
    if not baseline:
        raise RuntimeError(f'Baseline validation incomplete for {symbol}')

    baseline_pf = float(baseline.get('profit_factor') or 0.0)
    arima_pf = float(arima.get('profit_factor') or 0.0)
    baseline_return = float(baseline.get('return_percent') or 0.0)
    arima_return = float(arima.get('return_percent') or 0.0)
    arima_trades = int(arima.get('trades') or 0)
    arima_available = bool(arima)
    selected = (
        'D_arima_timeseries'
        if arima_available and arima_trades > 0 and arima_pf >= baseline_pf and arima_return >= baseline_return
        else 'A_baseline'
    )
    result = {
        'status': 'completed' if arima_available else 'completed_arima_insufficient_history',
        'version': 'arima-timeseries-oos-v1',
        'symbol': symbol,
        'horizon_days': 20,
        'oos_years': payload.get('oos_years'),
        'completed_at': datetime.now().astimezone().isoformat(),
        'selected_variant': selected,
        'baseline': baseline,
        'arima_timeseries': arima,
        'arima_error': arima_variant.get('error') if not arima_available else None,
        'arima_pf_uplift': round(arima_pf - baseline_pf, 4),
        'arima_return_uplift_pp': round(arima_return - baseline_return, 4),
        'report_path': str(output),
    }
    database = Database()
    try:
        database.execute(
            """UPDATE instruments
               SET meta=jsonb_set(COALESCE(meta::jsonb,'{}'::jsonb),
                                  '{arima_validation}', %s::jsonb, true)::json,
                   updated_at=NOW()
               WHERE id=%s""",
            (json.dumps(result), instrument_id),
        )
        database.commit()
    finally:
        database.close()
    log(
        f'ARIMA_TIMESERIES_DECISION symbol={symbol} selected={selected} '
        f'baseline_pf={baseline_pf:.3f} arima_pf={arima_pf:.3f} '
        f'baseline_return={baseline_return:.3f} arima_return={arima_return:.3f}'
    )
    return result


def postprocess_on_server(symbol: str) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan training:finalize-stock '
        f'{shlex.quote(symbol)} && '
        "php tmp/macbook_queue_status.php | head -22"
    )
    run([*SSH, remote], 'SERVER_CALIBRATION_FILTERS_RELEASE', symbol)


def refresh_prediction_scores_on_server(symbol: str, instrument_id: int) -> None:
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan predictions:apply-horizon-fusion && '
        f'sudo -u aktienki php artisan scores:recalculate --instrument={instrument_id} && '
        f'sudo -u aktienki php artisan stocks:classify-risk --instrument={instrument_id}'
    )
    run([*SSH, remote], 'SERVER_PREDICTION_FILTERS', symbol)


def send_completion_email(symbol: str, duration_seconds: int) -> None:
    try:
        sent = int(json.loads(EMAIL_COUNTER.read_text(encoding='utf-8')).get('sent', 0))
    except (FileNotFoundError, ValueError, TypeError, AttributeError):
        sent = 0
    if sent >= EMAIL_LIMIT:
        log(f'TRAINING_COMPLETION_EMAIL_SKIPPED symbol={symbol} reason=run_limit limit={EMAIL_LIMIT}')
        return
    remote = (
        'cd /home/aktienki/AktienKI/laravel && '
        'sudo -u aktienki php artisan training:send-completion '
        f'{shlex.quote(symbol)} --source=worker-pool --duration={max(0, duration_seconds)}'
    )
    run([*SSH, remote], 'TRAINING_COMPLETION_EMAIL', symbol)
    EMAIL_COUNTER.parent.mkdir(parents=True, exist_ok=True)
    EMAIL_COUNTER.write_text(json.dumps({'sent': sent + 1, 'limit': EMAIL_LIMIT}), encoding='utf-8')


def process(stock: dict) -> bool:
    symbol = str(stock['symbol'])
    pipeline_started = time.monotonic()
    missing_models = [int(value) for value in stock.get('missing_models') or []]
    missing_walk_forward = [int(value) for value in stock.get('missing_walk_forward') or []]
    missing_phase_filter = bool(stock.get('missing_phase_filter'))
    arima_revalidation_only = stock.get('reason') == 'mandatory_arima_pipeline_revalidation_20260826'
    log(f'PIPELINE_START symbol={symbol} missing_models={missing_models} missing_walk_forward={missing_walk_forward} missing_phase_filter={missing_phase_filter}')
    if stock.get('source_worker') == 'workstation':
        transfer_workstation_models(symbol)
    if missing_models:
        run([str(ENGINE), 'train-predict', '--symbol', symbol, '--benchmark', 'auto', '--timeframe', '1d', '--horizons', *[str(horizon) for horizon in sorted(missing_models)], '--minimum-historical-hit-rate', '0.55', '--minimum-profit-factor', '1.3', '--minimum-validation-trades', '15', '--maximum-drawdown', '0.40', '--position-side', 'long'], 'TRAIN_MODELS', symbol)
        missing_walk_forward = sorted(set(missing_walk_forward).union(missing_models))
    for horizon in missing_walk_forward:
        run([str(PYTHON), '-m', 'app.cli.backtest_walk_forward_heatmap', '--years', '3', '--history-years', '30', '--horizon', str(horizon), '--buy-threshold', '0.01', '--transaction-cost', '0.005', '--position-side', 'long', '--symbols', symbol], f'WALK_FORWARD_{horizon}D', symbol)
        if not walk_forward_complete(int(stock['id']), horizon):
            raise RuntimeError(f'Walk-forward {horizon}D produced no completed score with trades')
    if missing_phase_filter:
        run([
            str(PYTHON), 'scripts/test_single_stock_market_regimes.py',
            '--symbol', symbol, '--benchmark', 'auto', '--years', '10',
            '--regime-scheme', 'three', '--fold-epochs', '12',
            '--minimum-training-years', '5', '--minimum-regime-samples', '150',
            '--production-filter', '--minimum-filter-accuracy', '0.50',
        ], 'TRAIN_PHASE_FILTER', symbol)
        upload_phase_filter(symbol)
    run_and_document_arima_validation(symbol, int(stock['id']))
    if arima_revalidation_only:
        log(f'MODEL_ARTIFACT_UPLOAD_SKIPPED symbol={symbol} reason=already_registered_revalidation')
    else:
        upload_models(symbol)
    finalization_error = None
    try:
        postprocess_on_server(symbol)
    except RuntimeError as exception:
        finalization_error = str(exception)
        log(
            f'PIPELINE_DOCUMENTED_NOT_RELEASED symbol={symbol} '
            f'reason=server_quality_or_context_gate detail={finalization_error!r}'
        )
    released = finalization_error is None and instrument_is_active(int(stock['id']))
    usable_horizons = active_horizons(int(stock['id']))
    if released and set(HORIZONS).issubset(usable_horizons):
        if arima_revalidation_only:
            remote_prediction = (
                'cd /home/aktienki/AktienKI/python-engine-v2 && '
                'sudo -u aktienki .venv/bin/aktienki-engine predict-active '
                '--ai-type horizon --position-side long --limit 4 '
                f'--symbols {shlex.quote(symbol)} --recalculate --no-refresh'
            )
            run([*SSH, remote_prediction], 'FINAL_PRODUCTION_PREDICTION', symbol)
        else:
            run([str(ENGINE), 'predict-active', '--ai-type', 'horizon', '--position-side', 'long', '--limit', '4', '--symbols', symbol, '--recalculate', '--no-refresh'], 'FINAL_PRODUCTION_PREDICTION', symbol)
        refresh_prediction_scores_on_server(symbol, int(stock['id']))
    else:
        log(f'PIPELINE_DOCUMENTED_NOT_RELEASED symbol={symbol} active_horizons={sorted(usable_horizons)}')
    send_completion_email(symbol, round(time.monotonic() - pipeline_started))
    finish_continuation_job(
        stock.get('continuation_job_id'),
        'completed',
        {
            'symbol': symbol,
            'released_after_full_pipeline': released,
            'documented_not_released_reason': finalization_error,
        },
    )
    log(f'PIPELINE_COMPLETE symbol={symbol}')
    return True


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
    log('SEQUENTIAL_PIPELINE_START parallel=1')
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
            log(
                f'QUEUE_FINALIZED symbol={stock["symbol"]} '
                f'prediction_complete={str(prediction_complete).lower()} '
                f'cooldown={SUCCESS_COOLDOWN_SECONDS}s'
            )
        except Exception as exception:
            finish_continuation_job(stock.get('continuation_job_id'), 'failed', {'symbol': str(stock['symbol'])}, repr(exception))
            blocked_until[str(stock['symbol'])] = time.time() + 21600
            save_blocks(blocked_until)
            log(f'PIPELINE_FAILED symbol={stock["symbol"]} error={exception!r} blocked=21600s')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
