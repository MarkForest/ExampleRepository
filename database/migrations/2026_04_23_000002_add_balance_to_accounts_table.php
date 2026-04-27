<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', static function (Blueprint $table): void {
            $table->decimal('balance', 15, 2)->default(0.00)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', static function (Blueprint $table): void {
            $table->dropColumn('balance');
        });
    }
};
