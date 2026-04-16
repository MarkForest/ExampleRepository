<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', static function (Blueprint $table): void {
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('commission', 12, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('payments', static function (Blueprint $table): void {
            $table->dropColumn(['account_id', 'commission']);
        });
    }
};

