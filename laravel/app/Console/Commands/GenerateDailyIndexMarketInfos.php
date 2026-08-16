<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class GenerateDailyIndexMarketInfos extends Command
{
    protected $signature = 'markets:generate-index-infos {--force : Bereits vorhandene Marktinfos für heute neu erstellen}';

    protected $description = 'Erstellt täglich kompakte Marktinfos für alle aktiven Indizes mit GPT mini';

    public function handle(): int
    {
        if (! Schema::hasTable('daily_index_market_infos')) {
            $this->error('Bitte zuerst die Migrationen ausführen.');
            return self::FAILURE;
        }

        $apiKey = (string) env('OPENAI_API_KEY');
        if ($apiKey === '') {
            $this->error('OPENAI_API_KEY ist nicht konfiguriert.');
            return self::FAILURE;
        }

        $model = (string) env('OPENAI_INDEX_MARKET_INFO_MODEL', 'gpt-5.4-mini');
        $date = now()->toDateString();
        $latestPredictions = DB::table('predictions')->selectRaw('instrument_id, MAX(id) AS prediction_id')->groupBy('instrument_id');

        $indices = DB::table('market_indices as market_index')
            ->leftJoin('index_memberships as membership', function ($join): void {
                $join->on('membership.market_index_id', '=', 'market_index.id')->whereNull('membership.removed_at');
            })
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.instrument_id', '=', 'membership.instrument_id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->where('market_index.is_active', true)
            ->groupBy('market_index.id')
            ->orderBy('market_index.global_rank')
            ->select(['market_index.id', 'market_index.symbol', 'market_index.name', 'market_index.country', 'market_index.region'])
            ->selectRaw('COUNT(DISTINCT membership.instrument_id) AS members')
            ->selectRaw('COUNT(prediction.id) AS analyzed')
            ->selectRaw('AVG(prediction.prediction_score) AS score')
            ->selectRaw('AVG(prediction.confidence) AS confidence')
            ->selectRaw('AVG(prediction.risk_score) AS risk')
            ->selectRaw('AVG(((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return_20d')
            ->selectRaw("SUM(CASE WHEN UPPER(COALESCE(prediction.signal, 'HOLD')) = 'BUY' THEN 1 ELSE 0 END) AS buy_count")
            ->selectRaw("SUM(CASE WHEN UPPER(COALESCE(prediction.signal, 'HOLD')) IN ('WATCH', 'WAIT') THEN 1 ELSE 0 END) AS wait_count")
            ->selectRaw("SUM(CASE WHEN UPPER(COALESCE(prediction.signal, 'HOLD')) = 'HOLD' THEN 1 ELSE 0 END) AS hold_count")
            ->selectRaw("SUM(CASE WHEN UPPER(COALESCE(prediction.signal, 'HOLD')) = 'SELL' THEN 1 ELSE 0 END) AS sell_count")
            ->get();

        if (! $this->option('force')) {
            $existingIds = DB::table('daily_index_market_infos')->where('analysis_date', $date)->pluck('market_index_id');
            $indices = $indices->whereNotIn('id', $existingIds)->values();
        }
        if ($indices->isEmpty()) {
            $this->info('Alle Index-Marktinfos sind für heute bereits vorhanden.');
            return self::SUCCESS;
        }

        $snapshot = $indices->map(fn (object $index): array => [
            'id' => (int) $index->id,
            'symbol' => $index->symbol,
            'name' => $index->name,
            'country' => $index->country,
            'region' => $index->region,
            'members' => (int) $index->members,
            'analyzed' => (int) $index->analyzed,
            'score' => is_numeric($index->score) ? round((float) $index->score, 2) : null,
            'confidence_percent' => is_numeric($index->confidence) ? round((float) $index->confidence, 1) : null,
            'risk_percent' => is_numeric($index->risk) ? round((float) $index->risk, 1) : null,
            'expected_return_20d_percent' => is_numeric($index->expected_return_20d) ? round((float) $index->expected_return_20d, 2) : null,
            'signals' => ['buy' => (int) $index->buy_count, 'wait' => (int) $index->wait_count, 'hold' => (int) $index->hold_count, 'sell' => (int) $index->sell_count],
        ])->values();

        try {
            $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(120)->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => 'Du bist ein sachlicher Finanzmarkt-Redakteur. Nutze ausschließlich die gelieferten Kennzahlen, erfinde keine Nachrichten oder Ursachen und gib valides JSON ohne Markdown zurück.',
                'input' => 'Erstelle je Index eine aktuelle, gut verständliche Marktinfo mit 3 bis 4 kurzen Sätzen auf Deutsch und Englisch. Beschreibe Marktbreite, Signalverteilung, erwartete 20-Tage-Tendenz, Risiko und Datenabdeckung. Keine Anlageberatung. Format: [{"id":1,"market_info_de":"...","market_info_en":"..."}]. Daten: '.json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'max_output_tokens' => 5000,
                'metadata' => ['feature' => 'daily-index-market-info', 'analysis_date' => $date],
            ]);
            if ($response->failed()) throw new RuntimeException('HTTP '.$response->status().': '.(string) data_get($response->json(), 'error.message', 'OpenAI-Fehler'));

            $payload = $response->json();
            $raw = (string) ($payload['output_text'] ?? data_get($payload, 'output.0.content.0.text', ''));
            $raw = trim(preg_replace('/^```(?:json)?|```$/i', '', trim($raw)) ?? $raw);
            $start = strpos($raw, '['); $end = strrpos($raw, ']');
            if ($start !== false && $end !== false) $raw = substr($raw, $start, $end - $start + 1);
            $items = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $byId = collect($items)->filter(fn ($item) => is_array($item) && isset($item['id']))->keyBy('id');

            foreach ($snapshot as $input) {
                $item = $byId->get($input['id']);
                $german = trim((string) ($item['market_info_de'] ?? ''));
                if ($german === '') throw new RuntimeException('Marktinfo fehlt für Index-ID '.$input['id']);
                DB::table('daily_index_market_infos')->updateOrInsert(
                    ['market_index_id' => $input['id'], 'analysis_date' => $date],
                    ['model' => $model, 'market_info_de' => $german, 'market_info_en' => trim((string) ($item['market_info_en'] ?? '')) ?: null, 'input_snapshot' => json_encode($input), 'raw_response' => json_encode($item), 'created_at' => now(), 'updated_at' => now()]
                );
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info($snapshot->count().' tägliche Index-Marktinfos wurden mit '.$model.' erstellt.');
        return self::SUCCESS;
    }
}
