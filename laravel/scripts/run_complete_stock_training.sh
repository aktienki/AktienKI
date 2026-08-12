#!/bin/zsh
set -eu

if (( $# == 0 )); then
  echo "Usage: $0 SYMBOL [SYMBOL ...]" >&2
  exit 2
fi

LARAVEL_ROOT="${0:A:h:h}"
ENGINE_ROOT="${AKTIENKI_ENGINE_ROOT:-/Users/silviotaubert/Downloads/python-engine}"
ENGINE="$ENGINE_ROOT/.venv/bin/aktienki-engine"
PYTHON="$ENGINE_ROOT/.venv/bin/python"
symbols=("$@")

export TRAINING_YEARS="${TRAINING_YEARS:-30}"
BENCHMARK="${TRAINING_BENCHMARK:-^GSPC}"

for symbol in $symbols; do
  "$ENGINE" train-predict --symbol "$symbol" --benchmark "$BENCHMARK" --timeframe 1d \
    --horizons 5 10 15 20 --minimum-historical-hit-rate 0.55 \
    --minimum-profit-factor 1.3 --minimum-validation-trades 15 \
    --maximum-drawdown 0.40 --position-side long
done

cd "$LARAVEL_ROOT"
php artisan predictions:apply-horizon-fusion \
  --pipeline-version=horizon-fusion-v1 \
  --feature-version=triple_daily_macro_v1

cd "$ENGINE_ROOT"
for horizon in 5 10 15 20; do
  "$PYTHON" -m app.cli.backtest_walk_forward_heatmap --years 3 --history-years 30 \
    --horizon "$horizon" --buy-threshold 0.01 --transaction-cost 0.005 \
    --position-side long --symbols $symbols
done

cd "$LARAVEL_ROOT"
php artisan training:verify-complete $symbols --feature-version=triple_daily_macro_v1
