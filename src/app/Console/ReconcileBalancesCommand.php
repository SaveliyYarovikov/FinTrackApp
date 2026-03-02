<?php

declare(strict_types=1);

namespace App\Console;

use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Console\Command;

class ReconcileBalancesCommand extends Command
{
    protected $signature = 'fintrack:reconcile-balances {--fix : Overwrite cached balances with recalculated values}';

    protected $description = 'Checks account balance cache against transaction entries and optionally fixes mismatches.';

    public function handle(AccountBalanceService $accountBalanceService): int
    {
        $fix = (bool) $this->option('fix');
        $processed = 0;
        $mismatched = 0;

        Account::query()
            ->orderBy('id')
            ->chunkById(200, function ($accounts) use (&$processed, &$mismatched, $fix, $accountBalanceService): void {
                foreach ($accounts as $account) {
                    $processed++;
                    $result = $accountBalanceService->reconcile($account);

                    if ($result['is_consistent']) {
                        continue;
                    }

                    $mismatched++;
                    $this->warn(sprintf(
                        'Account #%d (%s): cached=%d recalculated=%d diff=%d',
                        $account->id,
                        $account->name,
                        $result['cached'],
                        $result['recalculated'],
                        $result['difference']
                    ));

                    if (! $fix) {
                        continue;
                    }

                    $accountBalanceService->applyRecalculatedBalance($account);
                    $this->line(sprintf('  Fixed account #%d.', $account->id));
                }
            });

        if ($mismatched === 0) {
            $this->info(sprintf('All %d account balances are consistent.', $processed));

            return self::SUCCESS;
        }

        if ($fix) {
            $this->info(sprintf('Processed %d accounts. Fixed %d mismatched balances.', $processed, $mismatched));

            return self::SUCCESS;
        }

        $this->error(sprintf('Processed %d accounts. Found %d mismatched balances.', $processed, $mismatched));

        return self::FAILURE;
    }
}
