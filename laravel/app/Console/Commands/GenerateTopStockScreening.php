<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateTopStockScreening extends Command
{
    protected $signature = 'stocks:screen-top100 {--with-ai : Generate bilingual explanations with GPT mini} {--user= : Owning user id} {--limit=10 : Number of ranked stocks} {--force : Rebuild even when a current daily result exists}';
    protected $description = 'Create a versioned deterministic Top-10 stock ranking with optional AI explanations';

    public function handle(): int
    {
        if (! Schema::hasTable('stock_screening_runs')) {
            $this->error('Bitte zuerst php artisan migrate --force ausführen.');
            return self::FAILURE;
        }

        $rankingLimit = max(1, (int) $this->option('limit'));
        $model = $this->option('with-ai') ? (string) env('OPENAI_SCREENING_MODEL', 'gpt-5.4-mini') : null;
        $bilingual = filter_var(env('OPENAI_SCREENING_BILINGUAL', false), FILTER_VALIDATE_BOOL);
        // One shared result per day keeps the external research cost predictable.
        // A user-specific run is only created when explicitly requested with --force.
        if (! $this->option('force')) {
            $todaysRun = DB::table('stock_screening_runs')
                ->where('universe', 'top'.$rankingLimit)
                ->whereDate('generated_at', now()->toDateString())
                ->when($model !== null, fn ($query) => $query->where('model', $model))
                ->when($model === null, fn ($query) => $query->whereNull('model'))
                ->latest('generated_at')
                ->first();
            if ($todaysRun !== null) {
                $this->info("Top-{$rankingLimit}-Auswertung ist für heute bereits vorhanden (Lauf {$todaysRun->id}).");
                return self::SUCCESS;
            }
        }

        $latestIds = DB::table('predictions as p')
            ->selectRaw('DISTINCT ON (p.instrument_id) p.id')
            ->orderBy('p.instrument_id')->orderByDesc('p.prediction_time')->orderByDesc('p.id');
        $rows = DB::table('predictions as p')
            ->join('instruments as i', 'i.id', '=', 'p.instrument_id')
            ->leftJoin('exchanges as e', 'e.id', '=', 'i.exchange_id')
            ->whereIn('p.id', $latestIds)->where('i.type', 'stock')->where('i.is_active', true)->whereNull('i.deleted_at')
            // Top-100 is a positive-watchlist ranking: BUY and WATCH only.
            ->whereIn(DB::raw('UPPER(COALESCE(p.signal, \'HOLD\'))'), ['BUY', 'WATCH'])
            ->select(['p.id as prediction_id','p.instrument_id','p.prediction_score','p.confidence','p.risk_score','p.drawdown_risk_factor','p.current_price','p.predicted_price_20d','p.signal','i.symbol','i.name','i.country','i.sector','e.code as exchange_code'])
            ->get()
            ->map(function (object $row): object {
                $score = (float) $row->prediction_score;
                $score10 = $score <= 1 ? $score * 10 : ($score <= 10 ? $score : $score / 10);
                $confidence = (float) $row->confidence; $confidence = $confidence <= 1 ? $confidence * 100 : $confidence;
                $risk = (float) ($row->risk_score ?? $row->drawdown_risk_factor ?? 0); $risk = $risk <= 1 ? $risk * 100 : $risk;
                $return = $row->current_price ? (((float) $row->predicted_price_20d - (float) $row->current_price) / (float) $row->current_price) * 100 : 0;
                $row->ranking_score = ($score10 / 10) * 40 + ($confidence / 100) * 25 + (max(0, 100 - min(100, $risk)) / 100) * 20 + (max(0, min(100, $return + 20)) / 100) * 15;
                $row->metrics = ['score_10' => round($score10, 2), 'confidence_percent' => round($confidence, 2), 'risk_percent' => round($risk, 2), 'expected_return_20d' => round($return, 2)];
                return $row;
            })->sortByDesc('ranking_score')->values()->take(100);

        $rows = $rows->take($rankingLimit)->values();
        $runId = DB::table('stock_screening_runs')->insertGetId(['user_id' => $this->option('user') ?: null, 'universe' => 'top'.$rankingLimit, 'model' => $model, 'item_count' => $rows->count(), 'parameters' => json_encode(['ranking' => 'score 40%, confidence 25%, risk 20%, expected return 15%', 'eligible_signals' => ['BUY', 'WATCH']]), 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $comments = [];
        if ($model && ($apiKey = (string) env('OPENAI_API_KEY')) !== '') {
            try {
                $input = $rows->map(fn (object $r, int $i) => ['rank' => $i + 1, 'symbol' => $r->symbol, 'name' => $r->name, 'signal' => $r->signal, ...$r->metrics])->values()->all();
                $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(120)->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'instructions' => $bilingual
                        ? 'Liefere valides JSON. Bewerte jede Aktie auf Grundlage der gelieferten Kennzahlen und einer kurzen aktuellen Webrecherche. Schreibe pro Aktie eine sachliche Begründung auf Deutsch und Englisch (je Sprache 6 bis 8 kurze Sätze) und gliedere sie zwingend stichpunktartig: zuerst "Pro:\n- ...\n- ...", danach "Contra:\n- ...\n- ...". Nenne nur überprüfbare aktuelle Unternehmens- oder Marktfaktoren und erfinde keine Kennzahlen. Format: [{"rank":1,"comment_de":"Pro:\n- ...\n- ...\nContra:\n- ...\n- ...","comment_en":"Pros:\n- ...\n- ...\nCons:\n- ...\n- ..."}]'
                        : 'Liefere valides JSON. Bewerte jede Aktie auf Grundlage der gelieferten Kennzahlen und einer kurzen aktuellen Webrecherche. Schreibe pro Aktie eine sachliche deutsche Begründung mit 6 bis 8 kurzen Sätzen und gliedere sie zwingend stichpunktartig: zuerst "Pro:\n- ...\n- ...", danach "Contra:\n- ...\n- ...". Nenne nur überprüfbare aktuelle Unternehmens- oder Marktfaktoren und erfinde keine Kennzahlen. Format: [{"rank":1,"comment_de":"Pro:\n- ...\n- ...\nContra:\n- ...\n- ..."}]',
                    'input' => json_encode($input, JSON_UNESCAPED_UNICODE),
                    'tools' => [['type' => 'web_search']],
                    'max_tool_calls' => 1,
                    'max_output_tokens' => 10000,
                ]);
                $payload = $response->json();
                $rawOutput = (string) ($payload['output_text'] ?? '');
                if ($rawOutput === '') {
                    $texts = [];
                    foreach ((array) ($payload['output'] ?? []) as $outputItem) {
                        foreach ((array) data_get($outputItem, 'content', []) as $contentItem) {
                            $text = data_get($contentItem, 'text');
                            if (is_string($text) && trim($text) !== '') $texts[] = $text;
                        }
                    }
                    $rawOutput = implode("\n", $texts);
                }
                $rawOutput = trim(preg_replace('/^```(?:json)?|```$/i', '', $rawOutput) ?? $rawOutput);
                $jsonStart = strpos($rawOutput, '[');
                $jsonEnd = strrpos($rawOutput, ']');
                if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
                    $rawOutput = substr($rawOutput, $jsonStart, $jsonEnd - $jsonStart + 1);
                }
                $decodedComments = json_decode($rawOutput, true);
                if (is_array($decodedComments) && ! array_is_list($decodedComments)) {
                    $decodedComments = $decodedComments['comments'] ?? $decodedComments['items'] ?? $decodedComments['stocks'] ?? $decodedComments;
                }
                $comments = collect(is_array($decodedComments) ? $decodedComments : [])
                    ->map(function ($comment, $key): array {
                        $comment = is_array($comment) ? $comment : [];
                        if (! isset($comment['rank']) && is_numeric($key)) $comment['rank'] = (int) $key + 1;
                        return $comment;
                    })->values()->all();
                $inputRate = str_contains(strtolower($model), '5.4-mini') ? .75 : .25;
                $outputRate = str_contains(strtolower($model), '5.4-mini') ? 4.5 : 2.0;
                DB::table('stock_screening_runs')->where('id', $runId)->update(['input_tokens' => data_get($payload, 'usage.input_tokens'), 'output_tokens' => data_get($payload, 'usage.output_tokens'), 'estimated_cost_usd' => ((float) data_get($payload, 'usage.input_tokens') * $inputRate + (float) data_get($payload, 'usage.output_tokens') * $outputRate) / 1000000]);
            } catch (Throwable $e) { $this->warn('AI-Erklärungen übersprungen: '.$e->getMessage()); }
        }
        $byRank = collect($comments)->keyBy('rank');
        foreach ($rows as $index => $row) { $comment = $byRank->get($index + 1, []); DB::table('stock_screening_items')->insert(['screening_run_id' => $runId, 'instrument_id' => $row->instrument_id, 'rank' => $index + 1, 'ranking_score' => $row->ranking_score, 'signal' => $row->signal, 'comment_de' => $comment['comment_de'] ?? null, 'comment_en' => $comment['comment_en'] ?? null, 'metrics' => json_encode($row->metrics), 'created_at' => now(), 'updated_at' => now()]); }
            $this->info("Top-{$rankingLimit}-Auswertung gespeichert (Lauf {$runId}, {$rows->count()} Aktien).");
        return self::SUCCESS;
    }
}
