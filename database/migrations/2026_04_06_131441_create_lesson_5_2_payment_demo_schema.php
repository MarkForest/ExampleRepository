<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12);
            $table->string('status', 32);
            $table->string('currency', 3)->default('UAH');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['payment_id', 'event_type'], 'audit_logs_payment_event_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payments');
    }
};
