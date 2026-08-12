<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $members = [
            '^GSPC' => ['GOOGL','AMZN','AVGO','META','TSLA','LLY','JPM','MU','WMT','AMD','V','XOM','JNJ','MA','CSCO','INTC','ABBV','BAC','COST','AMAT','CVX','KO','UNH','CAT','ORCL','GE','LRCX','PG','HD','MS','MRK','GS','NFLX','PM','PLTR','RTX','PANW','DELL','WFC','TXN','KLAC','ANET','AXP','C','TMO','IBM','AMGN','APH','CRWD','MCD'],
            '^GDAXI' => ['ADS.DE','AIR.DE','ALV.DE','BAS.DE','BAYN.DE','BEI.DE','BMW.DE','BNR.DE','CBK.DE','CON.DE','DB1.DE','DBK.DE','DHL.DE','DTE.DE','DTG.DE','ENR.DE','EOAN.DE','FRE.DE','G24.DE','HEI.DE','HEN3.DE','HNR1.DE','IFX.DE','MBG.DE','MRK.DE','MTX.DE','MUV2.DE','PAH3.DE','P911.DE','QIA.DE','RHM.DE','RWE.DE','SAP.DE','SHL.DE','SIE.DE','SRT3.DE','SY1.DE','VNA.DE','VOW3.DE','ZAL.DE'],
        ];
        $now = now();

        foreach ($members as $indexSymbol => $symbols) {
            $indexId = DB::table('market_indices')->where('symbol', $indexSymbol)->value('id');
            if (! $indexId) {
                continue;
            }
            DB::table('instruments')->where('type', 'stock')->whereIn('symbol', $symbols)->pluck('id')->each(
                fn ($instrumentId) => DB::table('index_memberships')->updateOrInsert(
                    ['market_index_id' => $indexId, 'instrument_id' => $instrumentId],
                    ['removed_at' => null, 'updated_at' => $now, 'created_at' => $now]
                )
            );
        }
    }

    public function down(): void
    {
        $indexIds = DB::table('market_indices')->whereIn('symbol', ['^GSPC', '^GDAXI'])->pluck('id');
        DB::table('index_memberships')->whereIn('market_index_id', $indexIds)->delete();
    }
};
