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
            $table->renameColumn('opening_balance_minor', 'opening_balance');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->renameColumn('balance_minor', 'balance');
        });

        Schema::table('budgets', function (Blueprint $table): void {
            $table->renameColumn('limit_minor', 'limit');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->renameColumn('amount_minor', 'amount');
        });

        Schema::table('recurring_operations', function (Blueprint $table): void {
            $table->renameColumn('amount_minor', 'amount');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->renameColumn('target_amount_minor', 'target_amount');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->renameColumn('start_balance_minor', 'start_balance');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->renameColumn('opening_balance', 'opening_balance_minor');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->renameColumn('balance', 'balance_minor');
        });

        Schema::table('budgets', function (Blueprint $table): void {
            $table->renameColumn('limit', 'limit_minor');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->renameColumn('amount', 'amount_minor');
        });

        Schema::table('recurring_operations', function (Blueprint $table): void {
            $table->renameColumn('amount', 'amount_minor');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->renameColumn('target_amount', 'target_amount_minor');
        });

        Schema::table('financial_goals', function (Blueprint $table): void {
            $table->renameColumn('start_balance', 'start_balance_minor');
        });
    }
};
