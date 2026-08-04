<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_automation_executions', function (Blueprint $table): void {
            $table->string('email_status', 20)->nullable()->after('details');
            $table->timestampTz('email_sent_at')->nullable()->after('email_status');
            $table->timestampTz('email_failed_at')->nullable()->after('email_sent_at');
            $table->text('email_failure_message')->nullable()->after('email_failed_at');
            $table->index(['email_status', 'created_at'], 'portfolio_automation_email_status_idx');
        });

        DB::table('portfolio_automation_executions')->whereNull('email_status')->update([
            'email_status' => 'ignored',
        ]);
        DB::statement("ALTER TABLE portfolio_automation_executions ALTER COLUMN email_status SET DEFAULT 'pending'");
        DB::statement('ALTER TABLE portfolio_automation_executions ALTER COLUMN email_status SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('portfolio_automation_executions', function (Blueprint $table): void {
            $table->dropIndex('portfolio_automation_email_status_idx');
            $table->dropColumn(['email_status', 'email_sent_at', 'email_failed_at', 'email_failure_message']);
        });
    }
};
