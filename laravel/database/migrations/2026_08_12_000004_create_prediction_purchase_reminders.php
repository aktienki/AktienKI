<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('prediction_purchase_reminders', function(Blueprint $t): void {$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('instrument_id')->constrained()->cascadeOnDelete();$t->foreignId('prediction_id')->constrained()->cascadeOnDelete();$t->unsignedSmallInteger('horizon_days');$t->decimal('purchase_price',24,10);$t->timestampTz('purchased_at');$t->date('remind_on');$t->string('status',20)->default('active');$t->timestampTz('notified_at')->nullable();$t->timestampsTz();$t->unique(['user_id','prediction_id','horizon_days']);}); }
 public function down(): void { Schema::dropIfExists('prediction_purchase_reminders'); }
};
