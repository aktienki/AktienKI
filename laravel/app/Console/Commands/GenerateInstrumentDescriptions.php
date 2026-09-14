<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateInstrumentDescriptions extends Command
{
    protected $signature = 'instruments:generate-descriptions
        {--limit=0 : Maximum number of instruments}
        {--force : Regenerate existing descriptions}
        {--sleep-ms=100 : Pause between requests}';

    protected $description = 'Generate bilingual business descriptions for active stocks';

    public function handle(): int
    {
        $apiKey = trim((string) config('aktienki.instrument_descriptions.grid_api_key'));
        if ($apiKey === '') {
            $this->error('GRID_API_KEY ist für die Firmenbeschreibungen nicht konfiguriert.');

            return self::FAILURE;
        }

        foreach (['business_summary', 'business_description', 'business_summary_en', 'business_description_en'] as $column) {
            if (! Schema::hasColumn('instruments', $column)) {
                $this->error("Die Spalte {$column} fehlt. Bitte zuerst php artisan migrate --force ausführen.");

                return self::FAILURE;
            }
        }

        $model = (string) config('aktienki.instrument_descriptions.grid_model', 'text-prime');
        $endpoint = (string) config('aktienki.instrument_descriptions.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');
        $query = DB::table('instruments')
            ->where('instruments.type', 'stock')
            ->where('instruments.is_active', true)
            ->whereNull('instruments.deleted_at')
            ->orderBy('instruments.id');

        if (! $this->option('force')) {
            $query->where(function ($builder): void {
                $builder->whereNull('business_summary')->orWhereNull('business_description')->orWhereNull('business_summary_en')->orWhereNull('business_description_en');
            });
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }
        $stocks = $query->get(['instruments.id', 'instruments.symbol', 'instruments.name', 'instruments.country', 'instruments.sector', 'instruments.industry', 'instruments.currency']);
        $this->info("{$stocks->count()} Aktien werden verarbeitet (Modell: {$model}).");

        $success = $failed = 0;
        foreach ($stocks as $stock) {
            try {
                $input = [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'country' => $stock->country,
                    'sector' => $stock->sector,
                    'industry' => $stock->industry,
                    'currency' => $stock->currency,
                ];

                $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(60)->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Du bist ein sachlicher Unternehmensredakteur. '
                                .'Erstelle für ein börsennotiertes Unternehmen jeweils eine deutsche und englische Version. '
                                .'compact_de/compact_en: jeweils 3-4 informative, gut lesbare Sätze für eine Screener-Karte. '
                                .'expanded_de/expanded_en: jeweils 6-8 kompakte, aber umfassende Sätze für eine Detailseite. '
                                .'Beschreibe Geschäftsmodell, wichtigste Produkte/Dienstleistungen, Kundengruppen, Hauptmärkte, Branche und relevante Wertschöpfung. '
                                .'Keine Anlageberatung, keine Kursprognose und keine erfundenen Details. Wenn Angaben fehlen, formuliere allgemein. '
                                .'Antworte ausschließlich mit einem einzigen JSON-Objekt gemäß dem vorgegebenen Schema - kein Fließtext davor oder danach.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => ['schema' => $this->resultSchema()],
                    ],
                    'max_tokens' => max(400, (int) config('aktienki.instrument_descriptions.max_output_tokens', 900)),
                ]);
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status().': '.(string) data_get($response->json(), 'error.message', 'The Grid liefert einen Fehler.'));
                }

                $raw = (string) data_get($response->json(), 'choices.0.message.content', '');
                $json = json_decode(trim($raw), true);
                $compact = trim((string) ($json['compact_de'] ?? ''));
                $expanded = trim((string) ($json['expanded_de'] ?? ''));
                $compactEn = trim((string) ($json['compact_en'] ?? ''));
                $expandedEn = trim((string) ($json['expanded_en'] ?? ''));
                if ($compact === '' || $expanded === '' || $compactEn === '' || $expandedEn === '') {
                    throw new \RuntimeException('Ungültige JSON-Antwort für '.$stock->symbol);
                }

                DB::table('instruments')->where('id', $stock->id)->update([
                    'business_summary' => $compact,
                    'business_description' => $expanded,
                    'business_summary_en' => $compactEn,
                    'business_description_en' => $expandedEn,
                    'business_description_model' => $model,
                    'business_description_updated_at' => now(),
                    'updated_at' => now(),
                ]);
                $success++;
                $this->line("✓ {$stock->symbol}");
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("{$stock->symbol}: {$exception->getMessage()}");
            }
            usleep(max(0, (int) $this->option('sleep-ms')) * 1000);
        }

        $this->info("Abgeschlossen: {$success} erfolgreich, {$failed} fehlgeschlagen.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resultSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['compact_de', 'expanded_de', 'compact_en', 'expanded_en'],
            'properties' => [
                'compact_de' => ['type' => 'string', 'maxLength' => 700],
                'expanded_de' => ['type' => 'string', 'maxLength' => 1400],
                'compact_en' => ['type' => 'string', 'maxLength' => 700],
                'expanded_en' => ['type' => 'string', 'maxLength' => 1400],
            ],
        ];
    }
}
