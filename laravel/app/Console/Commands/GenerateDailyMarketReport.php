<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GenerateDailyMarketReport extends Command
{
    protected $signature = 'markets:generate-daily-report {--force : Bericht für heute neu erzeugen}';
    protected $description = 'Erstellt den werktäglichen Marktbericht mit GPT Luna aus aktuellen Serving-Daten';

    public function handle(): int
    {
        $date = now('Europe/Berlin')->toDateString();
        if (! $this->option('force') && DB::table('daily_market_ai_analyses')->where('analysis_date', $date)->exists()) {
            $this->info("Marktbericht für {$date} ist bereits vorhanden.");
            return self::SUCCESS;
        }
        $apiKey = (string) env('OPENAI_API_KEY');
        if ($apiKey === '') {
            $this->error('OPENAI_API_KEY ist nicht konfiguriert.');
            return self::FAILURE;
        }

        $ranked = DB::connection('serving')->table('serving_instruments as instrument')
            ->join('serving_active_models as active', 'active.instrument_id', '=', 'instrument.id')
            ->join('serving_predictions as prediction', function ($join): void {
                $join->on('prediction.instrument_id', '=', 'instrument.id')->on('prediction.release_id', '=', 'active.release_id');
            })
            ->where('instrument.instrument_type', 'stock')->where('instrument.is_active', true)
            ->where('prediction.variant', 'standard')->where('prediction.horizon', 20)->whereNotNull('prediction.expected_return')
            ->select(['instrument.id as instrument_id', 'instrument.symbol', 'instrument.name', 'instrument.country_code', 'instrument.sector_code', 'instrument.home_index_symbol', 'prediction.signal', 'prediction.expected_return', 'prediction.confidence', 'prediction.risk_score', 'prediction.as_of'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument.id ORDER BY prediction.as_of DESC, prediction.id DESC) AS prediction_rank');
        $rows = DB::connection('serving')->query()->fromSub($ranked, 'ranked')->where('prediction_rank', 1)->get();
        if ($rows->count() < 5) {
            $this->error('Zu wenige aktuelle Serving-Prognosen: '.$rows->count());
            return self::FAILURE;
        }
        $percent = static fn ($value): ?float => ! is_numeric($value) ? null : round(abs((float)$value) <= 1 ? (float)$value * 100 : (float)$value, 2);
        $items = $rows->map(fn (object $row): array => [
            'symbol'=>$row->symbol, 'name'=>$row->name, 'country'=>$row->country_code, 'sector'=>$row->sector_code ?: 'Unbekannt',
            'index'=>$row->home_index_symbol, 'signal'=>strtoupper((string)$row->signal),
            'expected_return_20d_percent'=>round((float)$row->expected_return * 100, 2),
            'confidence_percent'=>$percent($row->confidence), 'risk'=>is_numeric($row->risk_score) ? round((float)$row->risk_score, 2) : null,
        ]);
        $sectors = $items->groupBy('sector')->map(fn ($group, $name): array => [
            'sector'=>$name, 'stocks'=>$group->count(), 'average_return_20d_percent'=>round((float)$group->avg('expected_return_20d_percent'), 2),
            'average_confidence_percent'=>round((float)$group->whereNotNull('confidence_percent')->avg('confidence_percent'), 1),
            'average_risk'=>round((float)$group->whereNotNull('risk')->avg('risk'), 2), 'signals'=>$group->countBy('signal')->all(),
        ])->values();
        $snapshot = [
            'as_of'=>$date, 'stocks'=>$items->count(),
            'market'=>['average_return_20d_percent'=>round((float)$items->avg('expected_return_20d_percent'), 2), 'positive_share_percent'=>round($items->where('expected_return_20d_percent', '>', 0)->count() / $items->count() * 100, 1), 'signals'=>$items->countBy('signal')->all()],
            'sectors'=>$sectors, 'strongest_stocks'=>$items->sortByDesc('expected_return_20d_percent')->take(15)->values(), 'weakest_stocks'=>$items->sortBy('expected_return_20d_percent')->take(15)->values(),
        ];
        $schema = $this->schema();
        try {
            $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(180)->post('https://api.openai.com/v1/responses', [
                'model'=>'gpt-5.6-luna',
                'instructions'=>'Du bist Chefstratege. Erstelle auf Deutsch einen aktuellen, sachlichen Gesamtmarktbericht. Verknüpfe den quantitativen Snapshot zwingend mit aktueller Web-Recherche. Nutze vorrangig Primärquellen, erfinde keine Fakten oder URLs, benenne Unsicherheiten und gib keine Anlageberatung.',
                'input'=>json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'tools'=>[['type'=>'web_search','search_context_size'=>'high','external_web_access'=>true]], 'tool_choice'=>'required', 'include'=>['web_search_call.action.sources'],
                'text'=>['format'=>['type'=>'json_schema','name'=>'daily_market_analysis','strict'=>true,'schema'=>$schema],'verbosity'=>'high'], 'max_output_tokens'=>7000,
            ]);
            if ($response->failed()) throw new RuntimeException('OpenAI HTTP '.$response->status().': '.(string)$response->json('error.message', $response->body()));
            $payload = $response->json();
            $outputText = (string)($payload['output_text'] ?? collect($payload['output'] ?? [])->flatMap(fn ($output) => $output['content'] ?? [])->firstWhere('type', 'output_text')['text'] ?? '');
            $report = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
            DB::transaction(function () use ($date, $snapshot, $payload, $report): void {
                DB::table('daily_market_ai_analyses')->updateOrInsert(['analysis_date'=>$date], [
                    'model'=>'gpt-5.6-luna', 'market_outlook'=>$report['market_outlook'], 'confidence'=>$report['confidence'], 'risk_level'=>$report['risk_level'],
                    'headline'=>$report['headline'], 'executive_summary'=>$report['executive_summary'], 'breadth_analysis'=>$report['breadth_analysis'],
                    'sector_analysis'=>json_encode($report['sector_analysis']), 'index_analysis'=>json_encode([]), 'opportunities'=>json_encode($report['opportunities']),
                    'risks'=>json_encode($report['risks']), 'watchlist'=>json_encode($report['watchlist']), 'input_snapshot'=>json_encode($snapshot),
                    'raw_response'=>json_encode(['external_research'=>true, 'response_id'=>$payload['id'] ?? null, 'output'=>$report]), 'created_at'=>now(), 'updated_at'=>now(),
                ]);
            });
        } catch (Throwable $error) {
            report($error); $this->error($error->getMessage()); return self::FAILURE;
        }
        $this->info("Marktbericht für {$date} mit gpt-5.6-luna gespeichert ({$items->count()} Aktien).");
        return self::SUCCESS;
    }

    private function schema(): array
    {
        $source = ['type'=>'object','properties'=>['title'=>['type'=>'string'],'publisher'=>['type'=>'string'],'url'=>['type'=>'string'],'published_at'=>['type'=>['string','null']],'used_for'=>['type'=>'string']],'required'=>['title','publisher','url','published_at','used_for'],'additionalProperties'=>false];
        return ['type'=>'object','properties'=>[
            'market_outlook'=>['type'=>'string','enum'=>['BULLISH','NEUTRAL','BEARISH']], 'confidence'=>['type'=>'integer','minimum'=>0,'maximum'=>100], 'risk_level'=>['type'=>'string','enum'=>['LOW','MEDIUM','HIGH']],
            'headline'=>['type'=>'string'], 'executive_summary'=>['type'=>'string'], 'breadth_analysis'=>['type'=>'string'],
            'sector_analysis'=>['type'=>'array','items'=>['type'=>'object','properties'=>['sector'=>['type'=>'string'],'outlook'=>['type'=>'string','enum'=>['BULLISH','NEUTRAL','BEARISH']],'summary'=>['type'=>'string']],'required'=>['sector','outlook','summary'],'additionalProperties'=>false]],
            'opportunities'=>['type'=>'array','items'=>['type'=>'string'],'maxItems'=>8], 'risks'=>['type'=>'array','items'=>['type'=>'string'],'maxItems'=>8],
            'watchlist'=>['type'=>'array','items'=>['type'=>'object','properties'=>['symbol'=>['type'=>'string'],'reason'=>['type'=>'string']],'required'=>['symbol','reason'],'additionalProperties'=>false],'maxItems'=>15],
            'sources'=>['type'=>'array','items'=>$source,'minItems'=>5,'maxItems'=>12],
        ], 'required'=>['market_outlook','confidence','risk_level','headline','executive_summary','breadth_analysis','sector_analysis','opportunities','risks','watchlist','sources'], 'additionalProperties'=>false];
    }
}
