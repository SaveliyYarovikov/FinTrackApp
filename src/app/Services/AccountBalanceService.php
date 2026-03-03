<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;

class AccountBalanceService
{
    public function recalculate(Account|int $account): int
    {
        $resolvedAccount = $this->resolveAccount($account);

        return (int) $resolvedAccount->balance;
    }

    /**
     * @return array{account: Account, cached: int, recalculated: int, difference: int, is_consistent: bool}
     */
    public function reconcile(Account|int $account): array
    {
        $resolvedAccount = $this->resolveAccount($account);
        $cached = (int) $resolvedAccount->balance;
        $recalculated = $this->recalculate($resolvedAccount);
        $difference = $recalculated - $cached;

        return [
            'account' => $resolvedAccount,
            'cached' => $cached,
            'recalculated' => $recalculated,
            'difference' => $difference,
            'is_consistent' => $difference === 0,
        ];
    }

    public function applyRecalculatedBalance(Account|int $account): Account
    {
        $resolvedAccount = $this->resolveAccount($account);
        $recalculated = $this->recalculate($resolvedAccount);

        $resolvedAccount->balance = $recalculated;
        $resolvedAccount->save();

        return $resolvedAccount->refresh();
    }

    private function resolveAccount(Account|int $account): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        return Account::query()->findOrFail($account);
    }
}
