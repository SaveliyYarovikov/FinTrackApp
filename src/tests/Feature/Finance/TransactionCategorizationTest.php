<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_transaction_with_own_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $category = Category::factory()->for($user)->create([
            'name' => 'Salary category',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store-income'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'occurred_at' => '2026-03-01',
            'description' => 'Salary with category',
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Income recorded.');

        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('description', 'Salary with category')
            ->firstOrFail();

        $this->assertSame($category->id, $transaction->category_id);
        $this->assertSame($category->id, $transaction->category()->firstOrFail()->id);
    }

    public function test_user_can_change_transaction_category_to_another_own_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $categoryA = Category::factory()->for($user)->create();
        $categoryB = Category::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->create([
                'amount' => 7_000,
                'category_id' => $categoryA->id,
                'description' => 'Category switch',
            ]);

        $response = $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'amount' => '70.00',
            'description' => 'Category switched',
            'category_id' => $categoryB->id,
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Transaction updated.');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $categoryB->id,
            'description' => 'Category switched',
        ]);
    }

    public function test_user_can_remove_category_from_existing_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $category = Category::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->create([
                'amount' => 3_500,
                'category_id' => $category->id,
                'description' => 'Remove category',
            ]);

        $response = $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'amount' => '35.00',
            'description' => 'Category removed',
            'category_id' => '',
        ]);

        $response
            ->assertRedirect(route('transactions.index', absolute: false))
            ->assertSessionHas('status', 'Transaction updated.');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => null,
            'description' => 'Category removed',
        ]);
    }

    public function test_user_cannot_assign_foreign_category_on_create_or_update(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $ownCategory = Category::factory()->for($user)->create();
        $foreignCategory = Category::factory()->for($otherUser)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->create([
                'amount' => 4_000,
                'category_id' => $ownCategory->id,
                'description' => 'Own category entry',
            ]);

        $this->actingAs($user)
            ->post(route('transactions.store-income'), [
                'account_id' => $account->id,
                'category_id' => $foreignCategory->id,
                'amount' => '50.00',
                'occurred_at' => '2026-03-05',
                'description' => 'Create with foreign category',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'description' => 'Create with foreign category',
        ]);

        $this->actingAs($user)
            ->from(route('transactions.edit', $transaction))
            ->put(route('transactions.update', $transaction), [
                'amount' => '40.00',
                'description' => 'Update with foreign category',
                'category_id' => $foreignCategory->id,
            ])
            ->assertSessionHasErrors(['entry']);

        $transaction->refresh();
        $this->assertSame($ownCategory->id, $transaction->category_id);
    }
}

