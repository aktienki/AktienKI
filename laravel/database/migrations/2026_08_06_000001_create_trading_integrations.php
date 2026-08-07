<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broker_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16)->default('demo');
            $table->string('name', 80);
            $table->text('credentials')->nullable();
            $table->string('external_account_id')->nullable();
            $table->boolean('trading_enabled')->default(false);
            $table->boolean('emergency_stop')->default(true);
            $table->decimal('max_order_value', 18, 2)->default(100);
            $table->decimal('daily_loss_limit', 18, 2)->default(100);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'environment']);
        });

        Schema::create('broker_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broker_connection_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 80);
            $table->string('broker_order_id')->nullable();
            $table->string('symbol', 40);
            $table->string('side', 8);
            $table->string('order_type', 16)->default('market');
            $table->decimal('quantity', 24, 8)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('limit_price', 24, 8)->nullable();
            $table->decimal('stop_loss', 24, 8)->nullable();
            $table->decimal('take_profit', 24, 8)->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['broker_connection_id', 'idempotency_key']);
        });

        Schema::create('messaging_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('whatsapp_cloud');
            $table->text('credentials')->nullable();
            $table->string('recipient', 40)->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_connections');
        Schema::dropIfExists('broker_orders');
        Schema::dropIfExists('broker_connections');
    }
};
