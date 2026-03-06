<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_current_users_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $otherAccount = Account::factory()->for($otherUser)->create([
            'type' => Account::TYPE_CARD,
        ]);

        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'visible-transaction',
        ]);
        Transaction::factory()->for($otherUser)->for($otherAccount)->create([
            'description' => 'hidden-foreign-transaction',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSeeText('visible-transaction')
            ->assertDontSeeText('hidden-foreign-transaction');
    }

    public function test_from_and_to_filters_limit_transactions_by_date_range(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);

        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'date-before-range',
            'occurred_at' => '2026-01-05 09:00:00',
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'date-in-range',
            'occurred_at' => '2026-01-20 09:00:00',
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'date-after-range',
            'occurred_at' => '2026-02-03 09:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'from' => '2026-01-10',
                'to' => '2026-01-31',
            ]))
            ->assertOk()
            ->assertSeeText('date-in-range')
            ->assertDontSeeText('date-before-range')
            ->assertDontSeeText('date-after-range');
    }

    public function test_account_filter_shows_only_transactions_from_selected_account(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create([
            'name' => 'Account A',
            'type' => Account::TYPE_CARD,
        ]);
        $accountB = Account::factory()->for($user)->create([
            'name' => 'Account B',
            'type' => Account::TYPE_SAVINGS,
        ]);

        Transaction::factory()->for($user)->for($accountA)->create([
            'description' => 'account-a-match',
        ]);
        Transaction::factory()->for($user)->for($accountB)->create([
            'description' => 'account-b-exclude',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'account_id' => $accountA->id,
            ]))
            ->assertOk()
            ->assertSeeText('account-a-match')
            ->assertDontSeeText('account-b-exclude');
    }

    public function test_category_filter_shows_only_transactions_from_selected_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $categoryA = Category::factory()->for($user)->create([
            'name' => 'Category A',
        ]);
        $categoryB = Category::factory()->for($user)->create([
            'name' => 'Category B',
        ]);

        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'category-a-match',
            'category_id' => $categoryA->id,
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'category-b-exclude',
            'category_id' => $categoryB->id,
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'category-null-exclude',
            'category_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'category_id' => $categoryA->id,
            ]))
            ->assertOk()
            ->assertSeeText('category-a-match')
            ->assertDontSeeText('category-b-exclude')
            ->assertDontSeeText('category-null-exclude');
    }

    public function test_type_filter_shows_only_income_or_expense_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);

        Transaction::factory()->for($user)->for($account)->income()->create([
            'description' => 'type-income-match',
            'occurred_at' => '2026-01-12 10:00:00',
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'type-expense-match',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -1_500,
            'occurred_at' => '2026-01-12 11:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', ['type' => Transaction::TYPE_INCOME]))
            ->assertOk()
            ->assertSeeText('type-income-match')
            ->assertDontSeeText('type-expense-match');

        $this->actingAs($user)
            ->get(route('transactions.index', ['type' => Transaction::TYPE_EXPENSE]))
            ->assertOk()
            ->assertSeeText('type-expense-match')
            ->assertDontSeeText('type-income-match');
    }

    public function test_combined_filters_are_applied_together(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $accountB = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
        ]);
        $categoryA = Category::factory()->for($user)->create();
        $categoryB = Category::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($accountA)->create([
            'description' => 'combo-match',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -2_500,
            'category_id' => $categoryA->id,
            'occurred_at' => '2026-02-10 08:00:00',
        ]);
        Transaction::factory()->for($user)->for($accountB)->create([
            'description' => 'combo-wrong-account',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -2_500,
            'category_id' => $categoryA->id,
            'occurred_at' => '2026-02-10 08:00:00',
        ]);
        Transaction::factory()->for($user)->for($accountA)->create([
            'description' => 'combo-wrong-category',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -2_500,
            'category_id' => $categoryB->id,
            'occurred_at' => '2026-02-10 08:00:00',
        ]);
        Transaction::factory()->for($user)->for($accountA)->income()->create([
            'description' => 'combo-wrong-type',
            'amount' => 2_500,
            'category_id' => $categoryA->id,
            'occurred_at' => '2026-02-10 08:00:00',
        ]);
        Transaction::factory()->for($user)->for($accountA)->create([
            'description' => 'combo-wrong-date',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -2_500,
            'category_id' => $categoryA->id,
            'occurred_at' => '2026-01-10 08:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'from' => '2026-02-01',
                'to' => '2026-02-28',
                'account_id' => $accountA->id,
                'category_id' => $categoryA->id,
                'type' => Transaction::TYPE_EXPENSE,
            ]))
            ->assertOk()
            ->assertSeeText('combo-match')
            ->assertDontSeeText('combo-wrong-account')
            ->assertDontSeeText('combo-wrong-category')
            ->assertDontSeeText('combo-wrong-type')
            ->assertDontSeeText('combo-wrong-date');
    }

    public function test_invalid_type_filter_does_not_break_page_and_is_ignored(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);

        Transaction::factory()->for($user)->for($account)->income()->create([
            'description' => 'invalid-type-income',
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'description' => 'invalid-type-expense',
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -1_000,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'type' => 'transfer',
            ]))
            ->assertOk()
            ->assertSeeText('invalid-type-income')
            ->assertSeeText('invalid-type-expense');
    }
}

