<?php

namespace App\Console\Commands;

use App\Services\EnglishTranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfills English translations for stock_ai_assessments and
 * external_buy_reviews - both are German-only free text today, so an
 * English-locale user sees German AI content regardless of language
 * setting. Same reasoning as business_description_en: generate once,
 * store, then just pick the right column at display time.
 */
final class TranslateAiTextToEnglish extends Command
{
    protected $signature = 'ai-text:translate-to-english {--limit=0} {--force : Retranslate rows that already have an English version}';

    protected $description = 'Translates existing German stock_ai_assessments/external_buy_reviews text into English via The Grid';

    public function handle(EnglishTranslationService $translator): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $this->translateAssessments($translator, $limit, $force);
        $this->translateReviews($translator, $limit, $force);

        return self::SUCCESS;
    }

    private function translateAssessments(EnglishTranslationService $translator, int $limit, bool $force): void
    {
        $query = DB::table('stock_ai_assessments')->orderBy('id');
        if (! $force) {
            $query->whereNull('summary_en');
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $rows = $query->get(['id', 'summary', 'opportunities', 'risks', 'key_factors']);
        $this->info("stock_ai_assessments: {$rows->count()} zu übersetzen.");

        $success = $failed = 0;
        foreach ($rows as $row) {
            try {
                $result = $translator->translate((string) $row->summary, [
                    'opportunities' => (array) json_decode((string) $row->opportunities, true),
                    'risks' => (array) json_decode((string) $row->risks, true),
                    'key_factors' => (array) json_decode((string) $row->key_factors, true),
                ]);
                DB::table('stock_ai_assessments')->where('id', $row->id)->update([
                    'summary_en' => $result['summary'],
                    'opportunities_en' => json_encode($result['lists']['opportunities'], JSON_UNESCAPED_UNICODE),
                    'risks_en' => json_encode($result['lists']['risks'], JSON_UNESCAPED_UNICODE),
                    'key_factors_en' => json_encode($result['lists']['key_factors'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $success++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("assessment #{$row->id}: {$exception->getMessage()}");
            }
        }
        $this->info("stock_ai_assessments: {$success} erfolgreich, {$failed} fehlgeschlagen.");
    }

    private function translateReviews(EnglishTranslationService $translator, int $limit, bool $force): void
    {
        $query = DB::table('external_buy_reviews')->where('status', 'completed')->orderBy('id');
        if (! $force) {
            $query->whereNull('summary_en');
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $rows = $query->get(['id', 'summary', 'positive_factors', 'risk_factors', 'key_findings']);
        $this->info("external_buy_reviews: {$rows->count()} zu übersetzen.");

        $success = $failed = 0;
        foreach ($rows as $row) {
            try {
                $findings = (array) json_decode((string) $row->key_findings, true);
                $claims = collect($findings)->map(fn ($finding) => (string) ($finding['claim'] ?? ''))->values()->all();

                $result = $translator->translate((string) $row->summary, [
                    'positive_factors' => (array) json_decode((string) $row->positive_factors, true),
                    'risk_factors' => (array) json_decode((string) $row->risk_factors, true),
                    'key_findings' => $claims,
                ]);

                // key_findings carries source_urls alongside each claim -
                // re-attach them to the translated claims, positionally.
                $translatedFindings = collect($result['lists']['key_findings'])
                    ->values()
                    ->map(fn (string $claim, int $index): array => [
                        'claim' => $claim,
                        'source_urls' => (array) ($findings[$index]['source_urls'] ?? []),
                    ])->all();

                DB::table('external_buy_reviews')->where('id', $row->id)->update([
                    'summary_en' => $result['summary'],
                    'positive_factors_en' => json_encode($result['lists']['positive_factors'], JSON_UNESCAPED_UNICODE),
                    'risk_factors_en' => json_encode($result['lists']['risk_factors'], JSON_UNESCAPED_UNICODE),
                    'key_findings_en' => json_encode($translatedFindings, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $success++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("review #{$row->id}: {$exception->getMessage()}");
            }
        }
        $this->info("external_buy_reviews: {$success} erfolgreich, {$failed} fehlgeschlagen.");
    }
}
