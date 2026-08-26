<?php

namespace App\Console\Commands;

use App\Services\LeveragedProductRiskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CalculateLeveragedProductRisk extends Command
{
    protected $signature = 'leveraged-products:calculate-risk
        {instrument? : Instrument ID or exact symbol}
        {--horizon=20 : Trading-day horizon}';

    protected $description = 'Calculate point-in-time loss and barrier risk matrices for leveraged products';

    public function handle(LeveragedProductRiskService $service): int
    {
        $input = (string) ($this->argument('instrument') ?: 'DBK.DE');
        $instrument = is_numeric($input)
            ? DB::table('instruments')->where('id', (int) $input)->first(['id', 'symbol', 'name'])
            : DB::table('instruments')->whereRaw('UPPER(symbol) = ?', [mb_strtoupper($input)])->first(['id', 'symbol', 'name']);

        if (! $instrument) {
            $this->error("Instrument {$input} not found.");
            return self::FAILURE;
        }

        $horizon = max(1, (int) $this->option('horizon'));
        $this->info("Calculating {$horizon}T leveraged-product risk for {$instrument->name} ({$instrument->symbol})...");
        $result = $service->calculate((int) $instrument->id, $horizon);

        $this->table(['Observations', 'Matrix cells', 'First signal', 'Last signal'], [[
            $result['observations'], $result['matrix_cells'], $result['first_date'], $result['last_date'],
        ]]);

        return self::SUCCESS;
    }
}
