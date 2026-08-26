"""Run the causal three-regime stock experiment with the 20-day target only."""
from __future__ import annotations

import scripts.test_single_stock_market_regimes as experiment


if __name__ == "__main__":
    experiment.HORIZONS = (20,)
    raise SystemExit(experiment.main())
