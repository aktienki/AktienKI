<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->foreignId('prediction_id')->nullable()->after('instrument_id')->constrained('predictions')->nullOnDelete();
            $table->index('prediction_id');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->dropForeign(['prediction_id']);
            $table->dropIndex(['prediction_id']);
            $table->dropColumn('prediction_id');
        });
    }
};
