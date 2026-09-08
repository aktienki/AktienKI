<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-sectional ("global") panel-model predictions, one row per
 * (model_version, instrument, trading day). Linked to instruments for
 * later correlation / factor analysis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('model_version', 80);
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->date('as_of_date');

            // model output
            $table->double('raw_score');            // predicted 20d return, demeaned vs universe
            $table->double('xsec_pctile');          // cross-sectional rank in [0,1] that day
            $table->unsignedTinyInteger('decile')->nullable();

            // realised outcomes (nullable near the right edge until the horizon elapses)
            $table->double('target_demeaned')->nullable();  // realised 20d return minus universe mean
            $table->double('fwd_ret_20d')->nullable();       // realised raw 20d forward return
            $table->double('univ_mean_fwd')->nullable();     // universe mean 20d forward return that day

            // selected input factors (for correlation tests)
            $table->double('ret_20')->nullable();
            $table->double('ret_120')->nullable();
            $table->double('rv_20')->nullable();
            $table->double('rv_50')->nullable();
            $table->double('beta_60')->nullable();
            $table->double('ivol_60')->nullable();
            $table->double('dd_120')->nullable();
            $table->double('rsi_14')->nullable();

            $table->timestamps();

            $table->unique(['model_version', 'instrument_id', 'as_of_date'], 'panel_pred_unique');
            $table->index(['instrument_id', 'as_of_date']);
            $table->index(['model_version', 'as_of_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_predictions');
    }
};
