<?php

namespace App\Http\Controllers;

use App\Services\AkiChatBudgetService;
use App\Services\FreeRegionalStockUniverseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AkiChatController extends Controller
{
    public function __invoke(Request $request, AkiChatBudgetService $budgetService): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'messages' => ['sometimes', 'array', 'max:8'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
            'filters' => ['sometimes', 'array'],
            'mode' => ['sometimes', 'in:standard,deep'],
        ]);

        $apiKey = (string) env('OPENAI_API_KEY');
        abort_unless($apiKey !== '', 503, 'OpenAI-Chat ist nicht konfiguriert.');

        $user = $request->user();
        abort_unless($user !== null, 401);
        $mode = $budgetService->modeFor($user, (string) ($data['mode'] ?? 'standard'));
        $model = (string) config("aki_chat.models.$mode");
        try {
            $usageRequestId = $budgetService->reserve($user, $mode);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'AKI_MONTHLY_BUDGET_EXHAUSTED') throw $exception;
            return response()->json([
                'message' => 'Dein monatliches AKI-Budget ist aufgebraucht. Es wird zum Monatsanfang automatisch erneuert.',
                'code' => 'aki_budget_exhausted',
                'budget' => $budgetService->summary($user),
            ], 429);
        }

        $history = collect($data['messages'] ?? [])
            ->map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
            ])->values()->all();
        $history[] = ['role' => 'user', 'content' => $data['question']];

        $tools = [[
            'type' => 'function',
            'name' => 'get_smart_selection_backtest',
            'description' => 'Liest die schreibgeschützte Backtest-Auswertung der Smart Selection für optionale Filter. Verwende dieses Werkzeug, wenn der Nutzer nach aktuellen Zahlen, Kandidaten oder einer sinnvollen Filtereinstellung fragt.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'score_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 10],
                    'confidence_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                    'profit_factor_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 10],
                    'volatility_max' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                    'country' => ['type' => ['string', 'null']],
                    'exchange' => ['type' => ['string', 'null']],
                    'sector' => ['type' => ['string', 'null']],
                    'index' => ['type' => ['string', 'null']],
                ],
                'required' => ['score_min', 'confidence_min', 'profit_factor_min', 'volatility_max', 'country', 'exchange', 'sector', 'index'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ], [
            'type' => 'function',
            'name' => 'set_prediction_filters',
            'description' => 'Erstellt eine konkrete Filterkonfiguration und wendet sie nach Zustimmung des Nutzers auf die Prognosetabelle an. Nutze dies, wenn der Nutzer ausdrücklich Filter setzen, konfigurieren oder anwenden möchte.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'score_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 10],
                    'confidence_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                    'drawdown_max' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 50],
                    'profit_factor_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 10],
                    'hit_rate_min' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                    'volatility_max' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                    'predicted_return_min' => ['type' => ['number', 'null'], 'minimum' => -50, 'maximum' => 100],
                    'country' => ['type' => ['string', 'null']],
                    'exchange' => ['type' => ['string', 'null']],
                    'sector' => ['type' => ['string', 'null']],
                    'index' => ['type' => ['string', 'null']],
                    'ai_type' => ['type' => ['string', 'null']],
                    'quality_tier' => ['type' => ['string', 'null']],
                    'signal' => ['type' => ['string', 'null']],
                    'symbols' => ['type' => ['array', 'null'], 'items' => ['type' => 'string', 'maxLength' => 20], 'maxItems' => 20],
                ],
                'required' => ['score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'hit_rate_min', 'volatility_max', 'predicted_return_min', 'country', 'exchange', 'sector', 'index', 'ai_type', 'quality_tier', 'signal', 'symbols'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ]];

        $allowedInstrumentIds = $budgetService->planCode($user) === 'free'
            ? app(FreeRegionalStockUniverseService::class)->instrumentIds($user)->all()
            : null;
        $currentList = $this->currentPredictionList($data['filters'] ?? [], $allowedInstrumentIds);
        $input = array_merge([
            ['role' => 'developer', 'content' => 'Aktuelle Filter und die sichtbare Prognoseliste der Seite (diese Daten darfst du direkt auswerten): '.json_encode(['filters' => $data['filters'] ?? [], 'predictions' => $currentList], JSON_UNESCAPED_UNICODE)."\nVerwende ausnahmslos aktive Aktien (instruments.is_active = true) ohne SLEEP-Status. Nenne, bewerte oder filtere niemals inaktive oder SLEEP-Aktien. Antworte immer in der aktuell ausgewählten Sprache ".(app()->getLocale() === 'en' ? 'Englisch' : 'Deutsch')." und in kurzen Stichpunkten. Behaupte niemals, dass du keinen Zugriff auf die Datenbank oder Liste hast. Wenn aktuelle Werte gefragt werden, nutze die übergebenen Listendaten oder rufe das Backtest-Werkzeug auf."],
        ], $history);
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => 'Du bist AKI, ein deutschsprachiger Assistent für die Prognosetabelle. Nutze ausschließlich aktive Aktien (instruments.is_active = true) ohne SLEEP-Status; inaktive oder SLEEP-Aktien darfst du weder nennen noch bewerten, empfehlen oder filtern. Nutze die übergebenen aktuellen Listendaten und Backtest-Werkzeuge aktiv. Empfehle und zeige ausschließlich Aktien mit positiver prognostizierter Rendite (predicted_return_min mindestens 0). Behaupte niemals, dass du keinen Zugriff auf die Datenbank oder Liste hast. Sobald du dem Nutzer konkrete Aktiensymbole nennst oder mehrere Aktien empfiehlst, rufe set_prediction_filters auf und übergib diese Symbole sowie predicted_return_min: 0, damit anschließend nur diese positiven Aktien angezeigt werden. Wenn der Nutzer ausdrücklich Filter setzen, anwenden oder konfigurieren möchte, rufe set_prediction_filters mit einer vollständigen, sinnvollen Konfiguration auf. Antworte ausschließlich in kurzen Stichpunkten (Zeilen mit •), niemals mit Gedankenstrichen als Satztrenner und ohne lange Absätze. Nutze get_smart_selection_backtest für Kennzahlen. Keine individuelle Anlageberatung und keine Kauf- oder Verkaufsempfehlungen.',
                'input' => $input,
                'tools' => $tools,
                'max_output_tokens' => $mode === 'deep' ? 900 : 220,
                'metadata' => ['feature' => 'smart-selection-chat'],
            ]);

        if ($response->failed()) {
            $budgetService->release($usageRequestId);
            report(new \RuntimeException('OpenAI chat request failed: '.$response->status().' '.$response->body()));
            return response()->json(['message' => 'Die KI ist gerade nicht erreichbar.'], 502);
        }

        $payload = $response->json();
        $allUsages = [(array) data_get($payload, 'usage', [])];
        $filterSuggestion = null;
        for ($round = 0; $round < 3; $round++) {
            $calls = collect(data_get($payload, 'output', []))->filter(fn (array $item): bool => data_get($item, 'type') === 'function_call');
            if ($calls->isEmpty()) break;
            $toolOutputs = [];
            foreach ($calls as $call) {
                $arguments = json_decode((string) data_get($call, 'arguments', '{}'), true) ?: [];
                if (data_get($call, 'name') === 'set_prediction_filters') {
                    $filterSuggestion = $this->normaliseFilters($arguments);
                    if (isset($filterSuggestion['symbols'])) {
                        $filterSuggestion['symbols'] = $this->activeSymbols($filterSuggestion['symbols'], $allowedInstrumentIds);
                    }
                    $result = ['filters' => $filterSuggestion, 'message' => 'Filterkonfiguration erstellt.'];
                } else {
                    $result = $this->backtestStats($arguments, $allowedInstrumentIds);
                }
                $toolOutputs[] = ['type' => 'function_call_output', 'call_id' => data_get($call, 'call_id'), 'output' => json_encode($result, JSON_UNESCAPED_UNICODE)];
            }
            $followUp = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(30)->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => 'Beziehe die Werkzeugdaten in deine deutsche Antwort ein. Antworte ausschließlich in kurzen Stichpunkten mit •, niemals in langen Absätzen. Keine Anlageberatung.',
                'previous_response_id' => data_get($payload, 'id'),
                'input' => $toolOutputs,
                'tools' => $tools,
                'max_output_tokens' => $mode === 'deep' ? 900 : 220,
            ]);
            if ($followUp->failed()) break;
            $payload = $followUp->json();
            $allUsages[] = (array) data_get($payload, 'usage', []);
        }
        $answer = data_get($payload, 'output_text');
        if (! is_string($answer) || trim($answer) === '') {
            $answer = collect(data_get($payload, 'output', []))
                ->flatMap(fn (array $item): array => (array) data_get($item, 'content', []))
                ->pluck('text')->filter()->implode("\n");
        }
        if ($filterSuggestion === null) $filterSuggestion = [];
        if (empty($filterSuggestion['symbols'])) {
            $answerUpper = strtoupper((string) $answer);
            $mentionedSymbols = collect($currentList)
                ->pluck('symbol')
                ->filter(fn ($symbol) => $symbol !== '' && str_contains($answerUpper, strtoupper((string) $symbol)))
                ->unique()->values()->all();
            if ($mentionedSymbols !== []) $filterSuggestion['symbols'] = $mentionedSymbols;
        }
        if ($filterSuggestion === []) $filterSuggestion = null;
        $answer = $this->formatAsBullets((string) $answer);

        $usage = $budgetService->mergeUsage(...$allUsages);
        try {
            $budgetService->complete($usageRequestId, $usage, $model);
        } catch (\Throwable $exception) {
            Log::warning('aki.chat.usage_persistence_failed', [
                'request_id' => $usageRequestId,
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);
            $budgetService->release($usageRequestId);
        }

        Log::info('aki.chat.usage', [
            'user_id' => $request->user()?->id,
            'model' => data_get($payload, 'model', env('OPENAI_CHAT_MODEL', 'gpt-5.4-mini')),
            'usage' => $usage,
            'mode' => $mode,
        ]);

        return response()->json([
            'answer' => trim((string) $answer),
            'model' => $model,
            'mode' => $mode,
            'usage' => $usage,
            'budget' => $budgetService->summary($user),
            'filter_suggestion' => $filterSuggestion,
        ]);
    }

    private function normaliseFilters(array $filters): array
    {
        $numeric = [
            'score_min' => [0, 10], 'confidence_min' => [0, 100], 'drawdown_max' => [0, 50],
            'profit_factor_min' => [0, 10], 'hit_rate_min' => [0, 100], 'volatility_max' => [0, 100],
            'predicted_return_min' => [-50, 100],
        ];
        $result = [];
        foreach ($numeric as $key => [$min, $max]) {
            if (isset($filters[$key]) && is_numeric($filters[$key])) $result[$key] = max($min, min($max, (float) $filters[$key]));
        }
        foreach (['country', 'exchange', 'sector', 'index', 'ai_type', 'quality_tier', 'signal'] as $key) {
            if (filled($filters[$key] ?? null)) $result[$key] = trim((string) $filters[$key]);
        }
        if (is_array($filters['symbols'] ?? null)) {
            $result['symbols'] = collect($filters['symbols'])->map(fn ($symbol) => strtoupper(trim((string) $symbol)))->filter(fn ($symbol) => preg_match('/^[A-Z0-9.\-]{1,20}$/', $symbol))->unique()->take(20)->values()->all();
            if ($result['symbols'] !== [] && ! array_key_exists('predicted_return_min', $result)) $result['predicted_return_min'] = 0;
        }
        return $result;
    }

    private function formatAsBullets(string $answer): string
    {
        $lines = preg_split('/\R+/', trim($answer), -1, PREG_SPLIT_NO_EMPTY);
        $lines = collect($lines)->map(function (string $line): string {
            $line = trim($line);
            $line = preg_replace('/^[\-*•·▪]+\s*/u', '', $line) ?: $line;
            return $line === '' ? '' : '• '.$line;
        })->filter()->values();
        return $lines->implode("\n");
    }

    private function currentPredictionList(array $filters, ?array $allowedInstrumentIds = null): array
    {
        return DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->when($allowedInstrumentIds !== null, fn ($query) => $query->whereIn('instrument.id', $allowedInstrumentIds ?: [-1]))
            ->whereRaw('(prediction.predicted_price_20d - prediction.current_price) > 0')
            ->whereRaw('prediction.id = (SELECT latest.id FROM predictions latest WHERE latest.instrument_id = prediction.instrument_id ORDER BY latest.prediction_time DESC NULLS LAST, latest.id DESC LIMIT 1)')
            ->when(filled($filters['country'] ?? null), fn ($q) => $q->where('instrument.country', strtoupper((string) $filters['country'])))
            ->when(filled($filters['sector'] ?? null), fn ($q) => $q->where('instrument.sector', (string) $filters['sector']))
            ->when(is_numeric($filters['score_min'] ?? null), fn ($q) => $q->where('prediction.prediction_score', '>=', (float) $filters['score_min']))
            ->orderByDesc('prediction.prediction_time')
            ->limit(40)
            ->select(['instrument.symbol', 'instrument.name', 'instrument.country', 'prediction.signal', 'prediction.prediction_score', 'prediction.confidence', 'prediction.current_price'])
            ->selectRaw('((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS predicted_return')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    private function backtestStats(array $filters, ?array $allowedInstrumentIds = null): array
    {
        if (isset($filters['index'])) {
            $indexCountry = DB::table('instruments')->where('type', 'index')->where('symbol', $filters['index'])->value('country');
            if (filled($indexCountry)) $filters['country'] = $indexCountry;
        }
        $run = DB::table('backtest_runs')->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('finished_at')->first();
        if ($run === null) return ['trades' => 0, 'message' => 'Keine abgeschlossene Backtest-Auswertung vorhanden.'];
        $rows = DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('trade.backtest_run_id', $run->id)
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->whereNull('instrument.deleted_at')
            ->when($allowedInstrumentIds !== null, fn ($query) => $query->whereIn('instrument.id', $allowedInstrumentIds ?: [-1]))
            ->when(isset($filters['country']), fn ($q) => $q->where('instrument.country', strtoupper($filters['country'])))
            ->when(isset($filters['exchange']), fn ($q) => $q->where('exchange.code', strtoupper($filters['exchange'])))
            ->when(isset($filters['sector']), fn ($q) => $q->where('instrument.sector', $filters['sector']))
            ->when(isset($filters['score_min']), fn ($q) => $q->where('trade.ki_score', '>=', min(10, max(0, (float) $filters['score_min']))))
            ->when(isset($filters['confidence_min']), fn ($q) => $q->where('trade.confidence', '>=', min(100, max(0, (float) $filters['confidence_min']))))
            ->when(isset($filters['volatility_max']) && (float) $filters['volatility_max'] < 100, fn ($q) => $q->whereRaw('ABS(trade.max_drawdown) * 100 <= ?', [(float) $filters['volatility_max']]))
            ->get(['trade.net_return', 'trade.max_drawdown']);
        $wins = $rows->where('net_return', '>', 0)->count();
        $loss = abs((float) $rows->where('net_return', '<', 0)->sum('net_return'));
        $profit = (float) $rows->where('net_return', '>', 0)->sum('net_return');
        return ['trades' => $rows->count(), 'winning_trades' => $wins, 'hit_rate' => $rows->count() ? round($wins / $rows->count() * 100, 1) : 0, 'profit_factor' => $loss ? round($profit / $loss, 2) : 0, 'max_drawdown' => round((float) $rows->max(fn ($r) => abs((float) $r->max_drawdown)) * 100, 1)];
    }

    private function activeSymbols(array $symbols, ?array $allowedInstrumentIds): array
    {
        return DB::table('instruments')->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('risk_status')->orWhere('risk_status', '<>', 'sleep'))
            ->whereIn(DB::raw('UPPER(symbol)'), collect($symbols)->map(fn ($symbol) => strtoupper((string) $symbol))->all())
            ->when($allowedInstrumentIds !== null, fn ($query) => $query->whereIn('id', $allowedInstrumentIds ?: [-1]))
            ->orderBy('symbol')->pluck('symbol')->all();
    }
}
