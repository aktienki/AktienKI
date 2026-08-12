<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateSignalChangeReport extends Command
{
    protected $signature = 'reports:signal-change {--symbol= : Optional symbol, otherwise latest transition}';
    protected $description = 'Create an extensive offline GPT-5.4 mini report for a recent signal transition.';

    public function handle(): int
    {
        $transition = $this->findTransition((string) $this->option('symbol'));
        if (! $transition) { $this->error('Kein Signalwechsel in den letzten 30 Tagen gefunden.'); return self::FAILURE; }
        $current = DB::table('predictions')->where('id', $transition->id)->first();
        $previous = DB::table('predictions')->where('id', $transition->previous_id)->first();
        $instrument = DB::table('instruments')->where('id', $current->instrument_id)->first();
        $fundamental = DB::table('instrument_fundamentals')->where('instrument_id', $instrument->id)->orderByDesc('snapshot_date')->first();
        $bars = DB::table('price_bars')->where('instrument_id', $instrument->id)->where('interval', '1d')->orderByDesc('bar_time')->limit(100)->get()->reverse()->values();
        $indicatorHistory = DB::table('technical_indicators as ti')
            ->leftJoin('feature_store as fs', function ($join): void {
                $join->on('fs.instrument_id', '=', 'ti.instrument_id')->on('fs.interval', '=', 'ti.interval')->on('fs.bar_time', '=', 'ti.bar_time');
            })
            ->where('ti.instrument_id', $instrument->id)->where('ti.interval', '1d')
            ->orderByDesc('ti.bar_time')->limit(80)->get([
                'ti.bar_time','ti.rsi_14','ti.stochastic_k','ti.adx_14','ti.macd_histogram','ti.momentum_10','ti.volatility_20','fs.target_return_20d'
            ])->reverse()->values();
        $recent = DB::table('predictions')->where('instrument_id', $instrument->id)->orderByDesc('prediction_time')->limit(12)->get();

        $compact = fn ($row): array => collect((array) $row)->only([
            'id','prediction_time','signal','current_price','predicted_price_5d','predicted_price_10d','predicted_price_20d','prediction_score','ai_score','confidence','risk_score','signal_quality_score','quality_band','quality_gate_passed','quality_gate_score','model_version_id','model_age_days','backtest_version','live_performance_status','live_performance_sample_size','recommendation_class'
        ])->all();
        $data = [
            'instrument' => collect((array) $instrument)->only(['symbol','name','country','currency','sector','industry','market_cap'])->all(),
            'transition' => ['from' => $transition->previous_signal, 'to' => $transition->signal, 'previous_at' => $transition->previous_time, 'current_at' => $transition->prediction_time],
            'previous_prediction' => $compact($previous), 'current_prediction' => $compact($current),
            'recent_predictions' => $recent->map($compact)->all(),
            'fundamentals' => $fundamental ? collect((array) $fundamental)->only(['snapshot_date','market_cap','enterprise_value','trailing_pe','forward_pe','price_to_book','price_to_sales','dividend_yield','profit_margin','operating_margin','return_on_equity','revenue_growth','debt_to_equity','current_ratio','free_cash_flow'])->all() : [],
            'bars' => $bars->map(fn ($bar): array => ['t' => (string) $bar->bar_time, 'o' => (float) $bar->open, 'h' => (float) $bar->high, 'l' => (float) $bar->low, 'c' => (float) $bar->close])->all(),
            'indicators' => $indicatorHistory->map(fn ($row): array => collect((array) $row)->map(fn ($value) => is_numeric($value) ? (float) $value : $value)->all())->all(),
        ];
        [$report, $usage] = $this->generateText($data);
        $data['report'] = $report;
        $data['report_html'] = $this->markdownHtml($report);
        $svg = $this->chartSvg($data['bars'], (float) ($current->current_price ?? 0), (float) ($current->predicted_price_20d ?? 0));
        $html = view('reports.signal-change', compact('data','svg'))->render();
        $options = new Options(); $options->set('isRemoteEnabled', false); $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options); $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4', 'portrait'); $pdf->render();
        $name = 'reports/signal-change-'.$data['instrument']['symbol'].'-'.now()->format('Ymd-His').'.pdf';
        Storage::disk('local')->put($name, $pdf->output());
        $cost = ((int) ($usage['input_tokens'] ?? 0) / 1000000) * 0.75 + ((int) ($usage['output_tokens'] ?? 0) / 1000000) * 4.50;
        DB::table('analysis_reports')->insert([
            'instrument_id' => $instrument->id,
            'prediction_id' => $current->id,
            'report_type' => 'signal_change',
            'symbol' => $data['instrument']['symbol'],
            'signal_from' => $data['transition']['from'],
            'signal_to' => $data['transition']['to'],
            'transition_at' => $data['transition']['current_at'],
            'prediction_at' => $data['transition']['current_at'],
            'model' => 'gpt-5.4-mini',
            'status' => 'completed',
            'report_text' => $report,
            'report_data' => json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR),
            'pdf_path' => $name,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'estimated_cost_usd' => $cost,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info(json_encode(['symbol' => $data['instrument']['symbol'], 'from' => $data['transition']['from'], 'to' => $data['transition']['to'], 'pdf' => storage_path('app/'.$name), 'model' => 'gpt-5.4-mini', 'usage' => $usage, 'estimated_cost_usd' => round($cost, 6)], JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function findTransition(string $symbol): ?object
    {
        $query = DB::table('predictions as p')->join('instruments as i','i.id','=','p.instrument_id')->where('i.type','stock')->where('p.prediction_time','>=',now()->subDays(30));
        if ($symbol !== '') $query->whereRaw('UPPER(i.symbol) = ?', [strtoupper($symbol)]);
        $rows = $query->select(['p.id','p.instrument_id','p.prediction_time','p.signal','i.symbol'])->selectRaw('LAG(p.id) OVER (PARTITION BY p.instrument_id,COALESCE(p.trained_model_id,0),p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_id')->selectRaw('LAG(p.signal) OVER (PARTITION BY p.instrument_id,COALESCE(p.trained_model_id,0),p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_signal')->selectRaw('LAG(p.prediction_time) OVER (PARTITION BY p.instrument_id,COALESCE(p.trained_model_id,0),p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_time')->orderByDesc('p.prediction_time')->get();
        return $rows->first(fn ($r) => $r->previous_signal !== null && strtoupper((string) $r->previous_signal) !== strtoupper((string) $r->signal));
    }

    private function generateText(array $data): array
    {
        $key = (string) env('OPENAI_API_KEY');
        if ($key === '') return ["Offline-Bericht ohne KI-Text. OPENAI_API_KEY ist nicht konfiguriert.", []];
        $prompt = "Erstelle einen sehr umfangreichen, sachlichen deutschsprachigen Analysebericht zu diesem echten Signalwechsel. Struktur mit Überschriften und Stichpunkten: 1) Executive Summary, 2) Was hat den Wechsel ausgelöst, 3) Preis-/Chartanalyse, 4) Indikatoren, 5) Modell- und Backtestqualität, 6) Fundamentaldaten, 7) Makro-/Sektor-Kontext soweit vorhanden, 8) Chancen, Risiken und Gegenargumente, 9) erwartbare News/Katalysatoren (klar als Szenario kennzeichnen), 10) konkrete Beobachtungspunkte und Schlussfazit. Keine erfundenen Werte; fehlende Daten offen benennen. Keine Anlageberatung. Nutze ausschließlich diese JSON-Daten:\n".json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
        $response = Http::withToken($key)->acceptJson()->asJson()->timeout(90)->post('https://api.openai.com/v1/responses', [
            'model' => 'gpt-5.4-mini', 'instructions' => 'Du bist ein präziser Finanzdaten-Redakteur. Antworte auf Deutsch mit Markdown-Überschriften und gut lesbaren Stichpunkten.', 'input' => $prompt, 'max_output_tokens' => 5000, 'metadata' => ['feature' => 'signal-change-report'],
        ]);
        if ($response->failed()) return ['KI-Bericht konnte nicht abgerufen werden (HTTP '.$response->status().'). Die Rohdaten dieses PDFs bleiben vollständig erhalten. API-Hinweis: '.(string) data_get($response->json(), 'error.message', 'unbekannter Fehler'), ['error' => $response->status()]];
        $text = (string) ($response->json('output_text') ?: 'Keine KI-Ausgabe erhalten.');
        $usage = (array) $response->json('usage', []);
        if ($usage === []) {
            // Responses API variants may omit usage; retain a transparent conservative estimate.
            $usage = ['input_tokens' => (int) ceil(strlen($prompt) / 4), 'output_tokens' => (int) ceil(strlen($text) / 4), 'estimated' => true];
        }
        return [$text, $usage];
    }

    private function chartSvg(array $bars, float $current, float $target): string
    {
        if (count($bars) < 2) return '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="220"><text x="20" y="40">Keine ausreichenden Kursdaten</text></svg>';
        $values = array_column($bars, 'c'); $min = min($values); $max = max($values); $range = max(0.0001, $max-$min); $w=720; $h=220; $points=[];
        foreach ($values as $i=>$v) $points[] = round(20+$i*($w-40)/(count($values)-1),1).','.round($h-20-($v-$min)/$range*($h-40),1);
        $targetY = $h-20-($target-$min)/$range*($h-40);
        return '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="220" viewBox="0 0 720 220"><rect width="720" height="220" fill="#f7fafc"/><polyline fill="none" stroke="#0e7490" stroke-width="3" points="'.implode(' ',$points).'"/><line x1="20" x2="700" y1="'.$targetY.'" y2="'.$targetY.'" stroke="#f59e0b" stroke-dasharray="7 5"/><text x="30" y="22" fill="#334155" font-size="13">Kursverlauf · 100 Handelstage</text><text x="535" y="'.max(16,min(210,$targetY-6)).'" fill="#b45309" font-size="12">Prognose 20T</text></svg>';
    }

    private function markdownHtml(string $markdown): string
    {
        $safe = e($markdown);
        $safe = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $safe);
        $safe = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $safe);
        $safe = preg_replace('/^#\s+(.+)$/m', '<h2>$1</h2>', $safe);
        $safe = preg_replace('/^[-*•]\s+(.+)$/m', '<li>$1</li>', $safe);
        $safe = preg_replace('/((?:<li>.*<\/li>\s*)+)/s', '<ul>$1</ul>', $safe);
        return nl2br($safe);
    }
}
