<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_income_and_expense_creation_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.create-income'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('transactions.create-expense'))
            ->assertOk();
    }

    public function test_user_can_create_income_and_account_balance_increases(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'balance' => 10_000,
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store-income'), [
            'account_id' => $account->id,
            'amount' => '123.45',
            'occurred_at' => '2026-03-01',
            'description' => 'Salary for March',
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Income recorded.');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 12_345,
            'description' => 'Salary for March',
            'category_id' => null,
        ]);

        $account->refresh();
        $this->assertSame(22_345, $account->balance);
    }

    public function test_user_can_create_expense_and_account_balance_decreases(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'balance' => 50_000,
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store-expense'), [
            'account_id' => $account->id,
            'amount' => '-25.60',
            'occurred_at' => '2026-03-02',
            'description' => 'Groceries',
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Expense recorded.');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -2_560,
            'description' => 'Groceries',
            'category_id' => null,
        ]);

        $account->refresh();
        $this->assertSame(47_440, $account->balance);
    }

    public function test_user_can_update_own_transaction_and_account_balance_is_adjusted_by_delta(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'balance' => 20_000,
        ]);
        $entry = Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->create([
                'amount' => 5_000,
                'description' => 'Old note',
            ]);

        $response = $this->actingAs($user)->put(route('transactions.update', $entry), [
            'amount' => '80.00',
            'description' => 'Updated note',
            'category_id' => null,
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Transaction updated.');

        $entry->refresh();
        $this->assertSame(8_000, $entry->amount);
        $this->assertSame('Updated note', $entry->description);
        $this->assertSame(Transaction::TYPE_INCOME, $entry->type);
        $this->assertSame($account->id, $entry->account_id);

        $account->refresh();
        $this->assertSame(23_000, $account->balance);
    }

    public function test_invalid_amount_sign_is_rejected_for_income_and_expense_creation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'balance' => 11_000,
        ]);

        $incomeResponse = $this->actingAs($user)
            ->from(route('transactions.create-income'))
            ->post(route('transactions.store-income'), [
                'account_id' => $account->id,
                'amount' => '0',
                'occurred_at' => '2026-03-03',
                'description' => 'Invalid income',
            ]);

        $incomeResponse
            ->assertSessionHasErrors(['amount']);

        $expenseResponse = $this->actingAs($user)
            ->from(route('transactions.create-expense'))
            ->post(route('transactions.store-expense'), [
                'account_id' => $account->id,
                'amount' => '10.00',
                'occurred_at' => '2026-03-04',
                'description' => 'Invalid expense',
            ]);

        $expenseResponse
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'description' => 'Invalid income',
        ]);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'description' => 'Invalid expense',
        ]);

        $account->refresh();
        $this->assertSame(11_000, $account->balance);
    }

    public function test_user_cannot_edit_or_update_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'type' => Account::TYPE_CARD,
            'balance' => 30_000,
        ]);
        $foreignEntry = Transaction::factory()
            ->for($otherUser)
            ->for($otherAccount)
            ->income()
            ->create([
                'amount' => 5_000,
                'description' => 'Foreign entry',
            ]);

        $this->actingAs($user)
            ->get(route('transactions.edit', $foreignEntry))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('transactions.update', $foreignEntry), [
                'amount' => '99.00',
                'description' => 'Should not update',
                'category_id' => null,
            ])
            ->assertForbidden();

        $foreignEntry->refresh();
        $this->assertSame(5_000, $foreignEntry->amount);
        $this->assertSame('Foreign entry', $foreignEntry->description);
    }
}

