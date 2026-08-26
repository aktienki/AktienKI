from __future__ import annotations

import argparse
from itertools import combinations
import json
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, balanced_accuracy_score, roc_auc_score
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

from app.cli.main import _import
from app.database.connection import Database


def feature_frame(bars: list, horizon: int) -> pd.DataFrame:
    rows = [{"date": b.timestamp.replace(tzinfo=None), "open": float(b.open), "high": float(b.high),
             "low": float(b.low), "close": float(b.close), "volume": float(b.volume or 0)}
            for b in bars if float(b.close) > 0]
    d = pd.DataFrame(rows).drop_duplicates("date").sort_values("date").set_index("date")
    c, h, l, v = d.close, d.high, d.low, d.volume
    ema12, ema26, ema50, ema200 = (c.ewm(span=n, adjust=False).mean() for n in (12, 26, 50, 200))
    macd = ema12 - ema26
    delta = c.diff(); gain = delta.clip(lower=0).ewm(alpha=1/14, adjust=False).mean()
    loss = (-delta.clip(upper=0)).ewm(alpha=1/14, adjust=False).mean()
    tr = pd.concat((h-l, (h-c.shift()).abs(), (l-c.shift()).abs()), axis=1).max(axis=1)
    atr = tr.ewm(alpha=1/14, adjust=False).mean()
    up, down = h.diff(), -l.diff()
    pdm = pd.Series(np.where((up > down) & (up > 0), up, 0), index=d.index)
    mdm = pd.Series(np.where((down > up) & (down > 0), down, 0), index=d.index)
    pdi, mdi = 100*pdm.ewm(alpha=1/14, adjust=False).mean()/atr, 100*mdm.ewm(alpha=1/14, adjust=False).mean()/atr
    mid, std = c.rolling(20).mean(), c.rolling(20).std()
    d["rsi14"] = 100 - 100/(1 + gain/loss.replace(0, np.nan))
    d["adx14"] = (100*(pdi-mdi).abs()/(pdi+mdi).replace(0, np.nan)).ewm(alpha=1/14, adjust=False).mean()
    d["ema_gap"] = ema50/ema200 - 1
    d["ema50_slope"] = ema50.pct_change(10)
    d["atr_pct"] = atr/c
    d["vol20"] = c.pct_change().rolling(20).std()*np.sqrt(252)
    d["macd_pct"] = macd/c
    d["momentum20"] = c.pct_change(20)
    d["bollinger_pos"] = (c-mid)/(2*std).replace(0, np.nan)
    d["volume_z"] = (v-v.rolling(20).mean())/v.rolling(20).std().replace(0, np.nan)
    d["target"] = (c.shift(-horizon) > c).astype(float)
    d.loc[c.shift(-horizon).isna(), "target"] = np.nan
    return d.replace([np.inf, -np.inf], np.nan).dropna(subset=["target", "ema_gap"])


def model() -> object:
    return make_pipeline(SimpleImputer(strategy="median"), StandardScaler(),
                         LogisticRegression(C=.5, max_iter=1000, class_weight="balanced"))


def score(train: pd.DataFrame, valid: pd.DataFrame, cols: tuple[str, ...]) -> float:
    m = model(); age = (train.index.max()-train.index).days.to_numpy()
    weights = np.exp(-age/(365.25*4))
    m.fit(train[list(cols)], train.target.astype(int), logisticregression__sample_weight=weights)
    p = m.predict_proba(valid[list(cols)])[:, 1]
    return balanced_accuracy_score(valid.target.astype(int), p >= .5)


def main() -> None:
    ap = argparse.ArgumentParser(); ap.add_argument("--symbol", default="IDXX")
    ap.add_argument("--years", type=int, default=30); ap.add_argument("--horizon", type=int, default=20)
    ap.add_argument("--minimum-training-years", type=int, default=8); ap.add_argument("--output", type=Path)
    args = ap.parse_args()
    with Database() as db: bars = _import(db, args.symbol, "1d", args.years, persist=False).bars
    data = feature_frame(bars, args.horizon)
    features = tuple(c for c in data.columns if c not in {"open","high","low","close","volume","target"})
    candidates = [x for n in range(2, 6) for x in combinations(features, n)]
    years = sorted(set(data.index.year)); folds=[]
    for year in years[args.minimum_training_years:]:
        outer_train, test = data[data.index.year < year], data[data.index.year == year]
        if len(test) < 30: continue
        prior = sorted(set(outer_train.index.year))[-3:]
        validation = outer_train[outer_train.index.year.isin(prior)]
        selection_train = outer_train[~outer_train.index.year.isin(prior)]
        if len(selection_train) < 500 or len(validation) < 100: continue
        ranked = sorted(((score(selection_train, validation, c), c) for c in candidates), reverse=True)
        chosen = ranked[0][1]; fitted=model()
        age=(outer_train.index.max()-outer_train.index).days.to_numpy(); weights=np.exp(-age/(365.25*4))
        fitted.fit(outer_train[list(chosen)], outer_train.target.astype(int), logisticregression__sample_weight=weights)
        p=fitted.predict_proba(test[list(chosen)])[:,1]; y=test.target.astype(int).to_numpy()
        baseline=model(); baseline.fit(outer_train[list(features)], outer_train.target.astype(int), logisticregression__sample_weight=weights)
        bp=baseline.predict_proba(test[list(features)])[:,1]
        folds.append({"year":year,"features":chosen,"selection_balanced_accuracy":round(ranked[0][0],4),
                      "accuracy":round(accuracy_score(y,p>=.5),4),"balanced_accuracy":round(balanced_accuracy_score(y,p>=.5),4),
                      "auc":round(roc_auc_score(y,p),4),
                      "baseline_all_accuracy":round(accuracy_score(y,bp>=.5),4),
                      "baseline_all_balanced_accuracy":round(balanced_accuracy_score(y,bp>=.5),4),
                      "baseline_all_auc":round(roc_auc_score(y,bp),4),"samples":len(y)})
    total=sum(f["samples"] for f in folds)
    result={"symbol":args.symbol,"horizon":args.horizon,"method":"nested-yearly-walk-forward",
            "recency_half_life_note":"exponential decay, 4-year time constant","features":features,"candidate_combinations":len(candidates),
            "folds":folds,"weighted_accuracy":sum(f["accuracy"]*f["samples"] for f in folds)/total if total else None,
            "weighted_balanced_accuracy":sum(f["balanced_accuracy"]*f["samples"] for f in folds)/total if total else None,
            "weighted_auc":sum(f["auc"]*f["samples"] for f in folds)/total if total else None,
            "baseline_all_weighted_accuracy":sum(f["baseline_all_accuracy"]*f["samples"] for f in folds)/total if total else None,
            "baseline_all_weighted_balanced_accuracy":sum(f["baseline_all_balanced_accuracy"]*f["samples"] for f in folds)/total if total else None,
            "baseline_all_weighted_auc":sum(f["baseline_all_auc"]*f["samples"] for f in folds)/total if total else None}
    print(json.dumps(result,indent=2,default=list));
    if args.output: args.output.write_text(json.dumps(result,indent=2,default=list))

if __name__ == "__main__": main()
