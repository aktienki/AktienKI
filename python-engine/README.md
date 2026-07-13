# AktienKI Engine 1.0 – Feature 01

## Installation

```bash
cd ~/AktienKI/python-engine
conda deactivate 2>/dev/null || true
rm -rf .venv
$(brew --prefix python@3.12)/bin/python3.12 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip setuptools wheel
python -m pip install -e .
cp .env.example .env
```

Dann PostgreSQL-Passwort in `.env` eintragen.

## Test

```bash
aktienki-engine import-market --symbol AAPL
```

## Erste zehn Instrumente

```bash
aktienki-engine import-market --limit 10
```

## Alle aktiven Aktien, ETFs und Indizes

```bash
aktienki-engine import-market
```

## Vollständiger Neuabgleich

```bash
aktienki-engine import-market --full
```

## Tests

```bash
pytest
```
