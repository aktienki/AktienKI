# Installation

```bash
cd /Users/silviotaubert/AktienKI
mv python-engine python-engine-beschaedigt
unzip ~/Downloads/AktienKI_Beta_0.8_Python_Recovery_001.zip
cd python-engine
/opt/homebrew/bin/python3.12 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r requirements.txt
python -m pytest tests/test_market_intelligence.py -q
```

Danach:

```bash
python run_market_intelligence.py
```
