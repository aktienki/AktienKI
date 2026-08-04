# Twelve Data fundamentals database contract

The Twelve Data synchronizer runs in the separate Python engine. Laravel does not call the
fundamentals endpoints during a web request and does not schedule their synchronization.

## Tables and upsert keys

- `instrument_fundamentals`: `(instrument_id, snapshot_date)`
- `instrument_financial_statements`: `(instrument_id, statement_type, fiscal_date, period)`
- `instrument_earnings`: `(instrument_id, earnings_date, period)`
- `instrument_dividends`: `(instrument_id, ex_date)`

`statement_type` is one of `income`, `balance_sheet`, or `cash_flow`. Use `unknown` when
Twelve Data does not supply a period. Store `retrieved_at` on every synchronization and keep
the provider response in `data` or `raw_data` for traceability.

## Normalized units

- Monetary values are stored in the currency and scale returned by Twelve Data.
- Ratios and percentages are stored as fractions: `0.125` means `12.5 %`.
- Per-share values are stored in the instrument currency.
- Dates represent the provider's fiscal, report, earnings, ex-dividend, record, or payment date.
- `reported_at` is the first publication timestamp known to the application. Do not replace it
  with a later retrieval timestamp, because backtests must avoid look-ahead bias.

## Snapshot payload compatibility

The `data` JSON on `instrument_fundamentals` remains compatible with existing Laravel filters.
The Python synchronizer should populate the normalized columns and may additionally include
the legacy camel-case keys such as `trailingPE`, `dividendYield`, `profitMargins`,
`returnOnEquity`, `revenueGrowth`, `debtToEquity`, and `freeCashflow`.

Laravel prefers normalized columns when both representations exist.
