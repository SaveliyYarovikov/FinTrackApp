<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('transfer_id');
        });

        Schema::table('recurring_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('from_account_id');
            $table->dropConstrainedForeignId('to_account_id');
            $table->dropColumn([
                'description',
                'schedule',
                'interval',
                'starts_at',
                'ends_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->char('transfer_id', 36)->nullable()->after('occurred_at');
            $table->index('transfer_id', 'ledger_entries_transfer_id_index');
        });

        Schema::table('recurring_operations', function (Blueprint $table): void {
            $table->foreignId('from_account_id')
                ->nullable()
                ->after('account_id')
                ->constrained('accounts')
                ->restrictOnDelete();

            $table->foreignId('to_account_id')
                ->nullable()
                ->after('from_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();

            $table->string('description', 255)->nullable()->after('category_id');
            $table->string('schedule', 32)->nullable()->after('description');
            $table->unsignedInteger('interval')->nullable()->after('schedule');
            $table->date('starts_at')->nullable()->after('interval');
            $table->date('ends_at')->nullable()->after('starts_at');
        });
    }
};
