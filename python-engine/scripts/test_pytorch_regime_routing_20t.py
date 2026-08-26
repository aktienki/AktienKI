from __future__ import annotations

import argparse, json, random
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import torch
from torch import nn

from app.cli.main import _import
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.market.benchmark import resolve_benchmark_symbol
from app.models.instrument_market_data import InstrumentMarketData
from test_stock_regime_ensemble_experts import bars_frame, PHASES
from test_standard_horizon_regime_experts_3stocks import raw_rows, standard_train, evaluate


HORIZON = 20
PHASE_INDEX = {name: index for index, name in enumerate(PHASES)}


def fit_gate(pool: pd.DataFrame, names: list[str], seed: int = 42, recency_half_life_years: float = 5.0):
    torch.manual_seed(seed); random.seed(seed); np.random.seed(seed)
    X = np.asarray(pool.X.tolist(), dtype=np.float32); y = np.asarray([PHASE_INDEX[x] for x in pool.economic_phase], dtype=np.int64)
    split = max(100, int(len(X) * .85)); mean, std = X[:split].mean(0), X[:split].std(0); std[std < 1e-8] = 1
    X = (X - mean) / std
    model = nn.Sequential(nn.Linear(X.shape[1], 48), nn.ReLU(), nn.Dropout(.12), nn.Linear(48, 24), nn.ReLU(), nn.Linear(24, len(PHASES)))
    optimizer = torch.optim.AdamW(model.parameters(), lr=8e-4, weight_decay=2e-4)
    counts = np.bincount(y[:split], minlength=len(PHASES)); class_weight = torch.tensor(len(y[:split]) / np.maximum(counts, 1), dtype=torch.float32)
    age_days = (pool.index[split - 1] - pool.index[:split]).days.to_numpy(dtype=float)
    recency = torch.tensor(np.power(0.5, age_days / (365.25 * recency_half_life_years)), dtype=torch.float32)
    tx, ty = torch.tensor(X[:split]), torch.tensor(y[:split]); vx, vy = torch.tensor(X[split:]), torch.tensor(y[split:])
    best, state, stale = float('inf'), None, 0
    for _ in range(80):
        model.train(); logits=model(tx); loss=(nn.functional.cross_entropy(logits,ty,weight=class_weight,reduction='none')*recency).sum()/recency.sum()
        optimizer.zero_grad(); loss.backward(); optimizer.step()
        model.eval()
        with torch.no_grad(): value=float(nn.functional.cross_entropy(model(vx),vy,weight=class_weight))
        if value < best - 1e-4: best=value; state={k:v.detach().clone() for k,v in model.state_dict().items()}; stale=0
        else:
            stale += 1
            if stale >= 8: break
    if state: model.load_state_dict(state)
    return model, mean, std


def gate_probabilities(model, mean, std, frame: pd.DataFrame) -> np.ndarray:
    X=(np.asarray(frame.X.tolist(),dtype=np.float32)-mean)/std
    model.eval()
    with torch.no_grad(): return torch.softmax(model(torch.tensor(X)),dim=1).numpy()


def phase_predictions(pool, test, feature_names, phase_column, recency_half_life_years=5.0):
    output=np.zeros((len(test),len(PHASES)),dtype=float); champions={}
    global_champion, global_scaler, _, _=standard_train(pool,HORIZON,feature_names,recency_half_life_years)
    fallback=np.asarray(global_champion.model.predict(global_scaler.transform(test.X.tolist())))
    for index,name in enumerate(PHASES):
        phase_pool=pool[pool[phase_column]==name]
        if len(phase_pool)<150: output[:,index]=fallback; champions[name]='global_fallback'; continue
        champion,scaler,_,_=standard_train(phase_pool,HORIZON,feature_names,recency_half_life_years)
        output[:,index]=champion.model.predict(scaler.transform(test.X.tolist())); champions[name]=champion.model_name
    return output,champions,fallback


def main():
    ap=argparse.ArgumentParser(); ap.add_argument('--symbol',default='IDXX'); ap.add_argument('--years',type=int,default=30)
    ap.add_argument('--training-windows',nargs='*',type=int,default=[])
    ap.add_argument('--recency-half-life-years',type=float,default=5.0)
    ap.add_argument('--test-years',nargs='*',type=int,default=list(range(2018,2027))); ap.add_argument('--output',type=Path,required=True); args=ap.parse_args()
    with Database() as db:
        repo=InstrumentRepository(db); instrument=repo.find_by_symbol(args.symbol); benchmark=resolve_benchmark_symbol(instrument,'auto')
        bars=list(_import(db,args.symbol,'1d',args.years,persist=False).bars); bench=list(_import(db,benchmark,'1d',args.years,persist=False).bars); agg=list(_import(db,'AGG','1d',args.years,persist=False).bars)
        u=repo.find_by_symbol('US2Y'); rows=db.fetch_all("SELECT bar_time,open,high,low,close,volume FROM price_bars WHERE instrument_id=%s AND interval='1d' ORDER BY bar_time",(u.id,))
        us2y=[InstrumentMarketData(instrument_id=u.id,timeframe='1d',timestamp=x['bar_time'],open=x['open'],high=x['high'],low=x['low'],close=x['close'],volume=int(x['volume'] or 0)) for x in rows]
    data=raw_rows(bars,bench,{'AGG':agg,'US2Y':us2y},HORIZON); names=data.attrs['feature_names']; data.attrs.clear()
    market=bars_frame(bench,'market'); data['rule_phase']=market.attrs['phase'].reindex(data.index)
    bc=market.attrs['close'].reindex(data.index,method='ffill'); forward=bc.shift(-HORIZON)/bc-1
    data['economic_phase']=np.where(forward>.02,'bull',np.where(forward<-.02,'stress','sideways')); data.loc[forward.isna(),'economic_phase']=np.nan; data=data.dropna(subset=['rule_phase','economic_phase'])
    windows=args.training_windows or [args.years]
    report={'symbol':args.symbol,'benchmark':benchmark,'horizon':HORIZON,'method':'nested_rule_vs_pytorch_hard_soft_routing','training_windows':windows,'recency_half_life_years':args.recency_half_life_years,'folds':[],'generated_at':datetime.now(timezone.utc).isoformat()}
    for window in windows:
      for year in args.test_years:
        start=pd.Timestamp(f'{year}-01-01'); pool=data[(data.index>=start-pd.DateOffset(years=window)) & (data.index<start-pd.offsets.BDay(HORIZON))]; test=data[data.index.year==year]
        if len(pool)<600 or len(test)<30: continue
        rule_pred,rule_champ,global_pred=phase_predictions(pool,test,names,'rule_phase',args.recency_half_life_years); economic_pred,econ_champ,_=phase_predictions(pool,test,names,'economic_phase',args.recency_half_life_years)
        rule_idx=np.asarray([PHASE_INDEX[x] for x in test.rule_phase]); rule_hard=rule_pred[np.arange(len(test)),rule_idx]
        gate,mean,std=fit_gate(pool,names,42+year+window,args.recency_half_life_years); probs=gate_probabilities(gate,mean,std,test); hard=economic_pred[np.arange(len(test)),probs.argmax(1)]; soft=(economic_pred*probs).sum(1); actual=test.y.to_numpy()
        fold={'training_window_years':window,'year':year,'training_samples':len(pool),'samples':len(test),'global':evaluate(actual,global_pred),'rule_hard':evaluate(actual,rule_hard),'pytorch_hard':evaluate(actual,hard),'pytorch_soft':evaluate(actual,soft),'rule_champions':rule_champ,'economic_champions':econ_champ,'gate_accuracy':float((probs.argmax(1)==np.asarray([PHASE_INDEX[x] for x in test.economic_phase])).mean()),'gate_distribution':{name:float(probs[:,i].mean()) for name,i in PHASE_INDEX.items()}}
        report['folds'].append(fold); args.output.parent.mkdir(parents=True,exist_ok=True); args.output.write_text(json.dumps(report,indent=2)); print('ROUTING_FOLD',window,year,json.dumps({k:round(fold[k]['direction_accuracy'],4) for k in ('global','rule_hard','pytorch_hard','pytorch_soft')}),flush=True)
    print('REPORT',args.output)

if __name__=='__main__': main()
