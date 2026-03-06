<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Models\Account;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = $request->user()
            ->accounts()
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('name')
            ->get();

        return view('accounts.index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): View
    {
        return view('accounts.create', [
            'currencies' => config('fintrack.supported_currencies', []),
            'accountTypes' => [Account::TYPE_CARD, Account::TYPE_SAVINGS],
            'defaultCurrency' => (string) config('fintrack.default_currency', 'EUR'),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $initialBalanceMinor = Money::parseMajorToMinor((string) $validated['balance']);

        Account::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'currency' => $validated['currency'],
            'type' => $validated['type'],
            'balance' => $initialBalanceMinor,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('status', 'Account created successfully.');
    }

    public function edit(Request $request, Account $account): View
    {
        $this->ensureOwnership($request, $account);

        return view('accounts.edit', [
            'account' => $account,
            'accountTypes' => [Account::TYPE_CARD, Account::TYPE_SAVINGS],
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->ensureOwnership($request, $account);

        $validated = $request->validated();

        $account->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'balance' => Money::parseMajorToMinor((string) $validated['balance']),
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('status', 'Account updated successfully.');
    }

    public function archive(Request $request, Account $account): RedirectResponse
    {
        $this->ensureOwnership($request, $account);

        if (! $account->isArchived()) {
            $account->archived_at = now();
            $account->save();
        }

        return redirect()
            ->route('accounts.index')
            ->with('status', 'Account archived.');
    }

    private function ensureOwnership(Request $request, Account $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 403);
    }
}
