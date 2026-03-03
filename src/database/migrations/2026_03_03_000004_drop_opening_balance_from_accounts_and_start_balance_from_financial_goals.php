<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('opening_balance');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->dropColumn('start_balance');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->bigInteger('opening_balance')->default(0)->after('type');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->bigInteger('start_balance')->default(0)->after('target_amount');
        });
    }
};
