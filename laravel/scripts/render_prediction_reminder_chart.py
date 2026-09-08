#!/usr/bin/env python3
import io, json, sys
from PIL import Image, ImageDraw, ImageFont

payload = json.load(sys.stdin)
history = [float(v) for v in payload.get("history", []) if v is not None and float(v) > 0]
forecast_map = {int(k): (float(v) if v is not None else None) for k, v in payload.get("forecasts", {}).items()}
horizons = [5, 10, 15, 20]
values = history + [forecast_map[d] for d in horizons if forecast_map.get(d) and forecast_map[d] > 0]
if not values: values = [0.0, 1.0]
w, h = 760, 300
img = Image.new("RGB", (w, h), "#0a192b")
draw = ImageDraw.Draw(img)
draw.rounded_rectangle((12, 12, w-12, h-12), 14, fill="#0e2638", outline="#29475e", width=2)
left, right, top, bottom = 54, 34, 40, 48
plot_w, plot_h = w-left-right, h-top-bottom
lo, hi = min(values), max(values)
pad = max((hi-lo)*0.14, max(hi, 1)*0.025)
lo, hi = lo-pad, hi+pad
scale = max(hi-lo, 0.0001)
y = lambda v: int(top + ((hi-v)/scale)*plot_h)
for n in range(5):
    yy = int(top + plot_h*n/4)
    draw.line((left, yy, w-right, yy), fill="#284658", width=1)
hist_w = plot_w*0.68
if len(history) > 1:
    pts = [(int(left+hist_w*i/(len(history)-1)), y(v)) for i,v in enumerate(history)]
    draw.line(pts, fill="#36d3eb", width=4, joint="curve")
current = history[-1] if history else next((forecast_map[d] for d in horizons if forecast_map.get(d)), 0)
px, py = int(left+hist_w), y(current)
step = (plot_w-hist_w)/4
for pos, days in enumerate(horizons):
    target = forecast_map.get(days)
    if not target or target <= 0: continue
    xx, yy = int(left+hist_w+step*(pos+1)), y(target)
    color = "#34d399" if target >= current else "#f97316"
    segments = 8
    for seg in range(0, segments, 2):
        a, b = seg/segments, min(1, (seg+1)/segments)
        draw.line((int(px+(xx-px)*a), int(py+(yy-py)*a), int(px+(xx-px)*b), int(py+(yy-py)*b)), fill=color, width=3)
    draw.ellipse((xx-6, yy-6, xx+6, yy+6), fill=color)
    draw.text((xx-10, h-37), f"{days}T", fill="#edf6f7")
    px, py = xx, yy
draw.text((left, 19), "Kursverlauf", fill="#edf6f7")
draw.text((w-170, 19), "Aktuelle Prognosen", fill="#91a8bb")
draw.text((left, h-37), "30 Handelstage", fill="#91a8bb")
out = io.BytesIO(); img.save(out, format="PNG", optimize=True); sys.stdout.buffer.write(out.getvalue())
