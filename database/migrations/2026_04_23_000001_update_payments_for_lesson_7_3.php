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
            $table->string('description', 500)->nullable()->after('currency');
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', static function (Blueprint $table): void {
            $table->dropColumn('description');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
