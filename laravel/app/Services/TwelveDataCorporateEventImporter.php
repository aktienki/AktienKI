<?php

namespace App\Services;

use App\Models\CorporateEvent;
use App\Models\CorporateEventImport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TwelveDataCorporateEventImporter
{
    public function __construct(private readonly TwelveDataService $marketData) {}

    public function syncEarnings(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $run = CorporateEventImport::create([
            'provider' => 'twelve_data', 'event_type' => 'earnings',
            'requested_from' => $from, 'requested_until' => $until,
            'status' => 'running', 'started_at' => now(),
        ]);

        try {
            $response = $this->request('earnings_calendar', [
                'start_date' => $from->toDateString(), 'end_date' => $until->toDateString(),
                'format' => 'JSON',
            ]);
            $payload = $response->json() ?: [];
            if (! $response->successful() || data_get($payload, 'status') === 'error' || data_get($payload, 'code')) {
                throw new RuntimeException((string) (data_get($payload, 'message') ?: 'Twelve Data HTTP '.$response->status()));
            }

            $universe = $this->universe();
            $records = $this->earningsRecords($payload);
            $matched = 0;

            DB::transaction(function () use ($records, $universe, $run, &$matched): void {
                foreach ($records as $record) {
                    $instrument = $this->matchInstrument($universe, $record);
                    if (! $instrument) {
                        continue;
                    }
                    $date = CarbonImmutable::parse($record['date'])->toDateString();
                    $key = implode(':', ['earnings', $instrument->id, $date, strtoupper((string) $record['symbol'])]);
                    CorporateEvent::updateOrCreate(
                        ['provider' => 'twelve_data', 'provider_event_key' => $key],
                        [
                            'instrument_id' => $instrument->id, 'import_id' => $run->id,
                            'event_type' => 'earnings', 'event_date' => $date,
                            'event_time' => $record['time'] ?? null,
                            'title' => __('Quartalszahlen :name', ['name' => $instrument->name]),
                            'eps_estimate' => $this->number($record['eps_estimate'] ?? null),
                            'eps_actual' => $this->number($record['eps_actual'] ?? null),
                            'surprise_percent' => $this->number($record['surprise_prc'] ?? null),
                            'currency' => $record['currency'] ?? $instrument->currency,
                            'provider_symbol' => $record['symbol'] ?? null,
                            'source_url' => 'https://twelvedata.com/docs/fundamentals/earnings',
                            'retrieved_at' => now(), 'data' => $record,
                        ],
                    );
                    $matched++;
                }
            });

            $run->update([
                'status' => 'completed', 'records_received' => $records->count(),
                'records_matched' => $matched, 'records_ignored' => max(0, $records->count() - $matched),
                'api_credits_used' => $this->integerHeader($response, 'api-credits-used'),
                'raw_payload' => $payload, 'finished_at' => now(),
            ]);

            return ['received' => $records->count(), 'matched' => $matched, 'ignored' => max(0, $records->count() - $matched)];
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'finished_at' => now()]);
            throw $exception;
        }
    }

    /**
     * Per-symbol variant of syncEarnings(). Twelve Data's global
     * earnings_calendar endpoint hard-caps its response at ~1200 records, so
     * for a wide date range our few hundred instruments only randomly land
     * in that page. Querying the per-symbol /earnings endpoint instead gets
     * complete history per stock, at the cost of one request per instrument.
     *
     * The per-symbol endpoint occasionally mixes in records with an
     * implausible EPS magnitude (a fraction of the stock's normal EPS) at an
     * irregular cadence between the real quarterly reports - the same kind
     * of scale-mismatch noise found manually in earnings_price_reactions
     * earlier. Those are filtered out via a plausibility check against the
     * instrument's already-trusted eps_actual history before upserting.
     */
    public function syncEarningsPerInstrument(int $outputsize = 20, int $sleepMs = 250, ?int $limit = null): array
    {
        $run = CorporateEventImport::create([
            'provider' => 'twelve_data', 'event_type' => 'earnings',
            'requested_from' => CarbonImmutable::today()->subYears(5), 'requested_until' => CarbonImmutable::today(),
            'status' => 'running', 'started_at' => now(),
        ]);

        $universe = $this->universe();
        if ($limit !== null) {
            $universe = $universe->take($limit);
        }

        $received = 0;
        $matched = 0;
        $filteredOut = 0;
        $failed = [];

        foreach ($universe as $instrument) {
            try {
                $providerSymbol = $this->marketData->providerSymbol((string) ($instrument->provider_symbol ?: $instrument->symbol));
                $response = $this->request('earnings', [
                    'symbol' => $providerSymbol,
                    'outputsize' => $outputsize,
                ]);
                $payload = $response->json() ?: [];
                if (! $response->successful() || data_get($payload, 'status') === 'error' || data_get($payload, 'code')) {
                    $failed[] = $instrument->symbol;
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }

                    continue;
                }

                $records = collect((array) ($payload['earnings'] ?? []))
                    ->filter(fn ($row) => is_array($row) && ! empty($row['date']))
                    ->sortBy('date')->values();
                $received += $records->count();

                $kept = $this->plausibleRecords($instrument, $records);
                $filteredOut += $records->count() - $kept->count();

                DB::transaction(function () use ($kept, $instrument, $run, &$matched): void {
                    foreach ($kept as $record) {
                        $date = CarbonImmutable::parse($record['date'])->toDateString();
                        $key = implode(':', ['earnings', $instrument->id, $date, strtoupper((string) $instrument->symbol)]);
                        CorporateEvent::updateOrCreate(
                            ['provider' => 'twelve_data', 'provider_event_key' => $key],
                            [
                                'instrument_id' => $instrument->id, 'import_id' => $run->id,
                                'event_type' => 'earnings', 'event_date' => $date,
                                'event_time' => $record['time'] ?? null,
                                'title' => __('Quartalszahlen :name', ['name' => $instrument->name]),
                                'eps_estimate' => $this->number($record['eps_estimate'] ?? null),
                                'eps_actual' => $this->number($record['eps_actual'] ?? null),
                                'surprise_percent' => $this->number($record['surprise_prc'] ?? null),
                                'currency' => $instrument->currency,
                                'provider_symbol' => $instrument->provider_symbol ?: $instrument->symbol,
                                'source_url' => 'https://twelvedata.com/docs/fundamentals/earnings',
                                'retrieved_at' => now(), 'data' => $record,
                            ],
                        );
                        $matched++;
                    }
                });
            } catch (Throwable) {
                $failed[] = $instrument->symbol;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $run->update([
            'status' => 'completed', 'records_received' => $received,
            'records_matched' => $matched, 'records_ignored' => max(0, $received - $matched),
            'finished_at' => now(),
        ]);

        return [
            'instruments' => $universe->count(), 'received' => $received,
            'matched' => $matched, 'filtered_out' => $filteredOut, 'failed' => $failed,
        ];
    }

    private function plausibleRecords(object $instrument, Collection $records): Collection
    {
        $historicalMedian = $this->historicalMedianAbsEps($instrument->id);

        $kept = collect();
        $lastKeptDate = null;

        foreach ($records as $record) {
            $eps = $this->number($record['eps_actual'] ?? null) ?? $this->number($record['eps_estimate'] ?? null);

            if ($historicalMedian !== null && $eps !== null && $eps !== 0.0) {
                $ratio = abs($eps) / $historicalMedian;
                if ($ratio < 0.3 || $ratio > 4.0) {
                    continue;
                }
            }

            $date = CarbonImmutable::parse($record['date']);
            if ($lastKeptDate !== null && abs($date->diffInDays($lastKeptDate)) < 45) {
                continue;
            }

            $kept->push($record);
            $lastKeptDate = $date;
        }

        return $kept->values();
    }

    private function historicalMedianAbsEps(int $instrumentId): ?float
    {
        $values = CorporateEvent::query()->where('instrument_id', $instrumentId)->where('event_type', 'earnings')
            ->whereNotNull('eps_actual')->orderBy('event_date', 'desc')->limit(12)
            ->pluck('eps_actual')->map(fn ($v) => abs((float) $v))->filter(fn ($v) => $v > 0)->sort()->values();

        if ($values->count() < 3) {
            return null;
        }

        $mid = intdiv($values->count(), 2);

        return $values->count() % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }

    private function universe(): Collection
    {
        return DB::table('instruments as instrument')->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('instrument.type', 'stock')->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
            ->get(['instrument.id', 'instrument.symbol', 'instrument.provider_symbol', 'instrument.name', 'instrument.currency', 'exchange.code as exchange_code', 'exchange.mic as exchange_mic'])
            ->map(function (object $instrument): object {
                $instrument->keys = collect([$instrument->symbol, $instrument->provider_symbol])
                    ->filter()->map(fn ($symbol) => strtoupper($this->marketData->providerSymbol((string) $symbol)))->unique()->all();

                return $instrument;
            });
    }

    private function earningsRecords(array $payload): Collection
    {
        return collect((array) ($payload['earnings'] ?? []))->flatMap(fn ($rows, $date) => collect((array) $rows)
            ->filter(fn ($row) => is_array($row) && ! empty($row['symbol']))
            ->map(fn (array $row): array => [...$row, 'date' => $row['date'] ?? $date]))->values();
    }

    private function matchInstrument(Collection $universe, array $record): ?object
    {
        $symbol = strtoupper($this->marketData->providerSymbol((string) ($record['symbol'] ?? '')));
        $candidates = $universe->filter(fn (object $instrument) => in_array($symbol, $instrument->keys, true));
        if ($candidates->count() <= 1) {
            return $candidates->first();
        }
        $mic = strtoupper((string) ($record['mic_code'] ?? ''));
        $exchange = strtoupper((string) ($record['exchange'] ?? ''));

        return $candidates->first(fn (object $instrument) => ($mic && strtoupper((string) $instrument->exchange_mic) === $mic)
            || ($exchange && strtoupper((string) $instrument->exchange_code) === $exchange)) ?: $candidates->first();
    }

    private function request(string $endpoint, array $parameters): Response
    {
        return Http::baseUrl((string) config('aktienki.twelve_data.base_url'))
            ->withHeaders(['Authorization' => 'apikey '.config('aktienki.twelve_data.api_key')])
            ->acceptJson()->retry(2, 500, throw: false)->timeout(45)->get($endpoint, $parameters);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integerHeader(Response $response, string $name): ?int
    {
        $value = $response->header($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
