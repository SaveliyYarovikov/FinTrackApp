<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringOperationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_create_and_edit_recurring_operation_pages(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $operation = RecurringOperation::factory()->create([
            'user_id' => $user->id,
            'name' => 'Monthly salary',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => 300_000,
            'account_id' => $account->id,
            'category_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('recurring-operations.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('recurring-operations.edit', $operation))
            ->assertOk();
    }

    public function test_user_can_create_recurring_operation_template(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('recurring-operations.store'), [
            'name' => 'Monthly salary',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => '3000.50',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response
            ->assertRedirect(route('recurring-operations.index', absolute: false))
            ->assertSessionHas('status', 'Recurring operation created.');

        $this->assertDatabaseHas('recurring_operations', [
            'user_id' => $user->id,
            'name' => 'Monthly salary',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => 300_050,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_user_can_update_own_recurring_operation_template(): void
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
        $operation = RecurringOperation::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old template',
            'type' => RecurringOperation::TYPE_EXPENSE,
            'amount' => 9_000,
            'account_id' => $accountA->id,
            'category_id' => $categoryA->id,
        ]);

        $response = $this->actingAs($user)->put(route('recurring-operations.update', $operation), [
            'name' => 'Updated template',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => '55.25',
            'account_id' => $accountB->id,
            'category_id' => $categoryB->id,
        ]);

        $response
            ->assertRedirect(route('recurring-operations.index', absolute: false))
            ->assertSessionHas('status', 'Recurring operation updated.');

        $this->assertDatabaseHas('recurring_operations', [
            'id' => $operation->id,
            'user_id' => $user->id,
            'name' => 'Updated template',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => 5_525,
            'account_id' => $accountB->id,
            'category_id' => $categoryB->id,
        ]);
    }

    public function test_archived_account_cannot_be_used_for_creating_or_updating_recurring_operation(): void
    {
        $user = User::factory()->create();
        $activeAccount = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'archived_at' => null,
        ]);
        $archivedAccount = Account::factory()->for($user)->archived()->create([
            'type' => Account::TYPE_SAVINGS,
        ]);
        $operation = RecurringOperation::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing template',
            'type' => RecurringOperation::TYPE_EXPENSE,
            'amount' => 4_000,
            'account_id' => $activeAccount->id,
            'category_id' => null,
        ]);

        $this->actingAs($user)
            ->from(route('recurring-operations.create'))
            ->post(route('recurring-operations.store'), [
                'name' => 'Create with archived account',
                'type' => RecurringOperation::TYPE_INCOME,
                'amount' => '10.00',
                'account_id' => $archivedAccount->id,
                'category_id' => null,
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->assertDatabaseMissing('recurring_operations', [
            'user_id' => $user->id,
            'name' => 'Create with archived account',
        ]);

        $this->actingAs($user)
            ->from(route('recurring-operations.edit', $operation))
            ->put(route('recurring-operations.update', $operation), [
                'name' => 'Updated with archived account',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '12.00',
                'account_id' => $archivedAccount->id,
                'category_id' => null,
            ])
            ->assertSessionHasErrors(['account_id']);

        $operation->refresh();
        $this->assertSame('Existing template', $operation->name);
        $this->assertSame($activeAccount->id, $operation->account_id);
    }

    public function test_user_cannot_use_foreign_account_or_category_for_create_or_update(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownAccount = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $ownCategory = Category::factory()->for($user)->create();
        $foreignAccount = Account::factory()->for($otherUser)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $foreignCategory = Category::factory()->for($otherUser)->create();
        $operation = RecurringOperation::factory()->create([
            'user_id' => $user->id,
            'name' => 'Owned template',
            'type' => RecurringOperation::TYPE_EXPENSE,
            'amount' => 7_500,
            'account_id' => $ownAccount->id,
            'category_id' => $ownCategory->id,
        ]);

        $this->actingAs($user)
            ->from(route('recurring-operations.create'))
            ->post(route('recurring-operations.store'), [
                'name' => 'Create with foreign account',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '11.00',
                'account_id' => $foreignAccount->id,
                'category_id' => $ownCategory->id,
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->actingAs($user)
            ->from(route('recurring-operations.create'))
            ->post(route('recurring-operations.store'), [
                'name' => 'Create with foreign category',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '11.00',
                'account_id' => $ownAccount->id,
                'category_id' => $foreignCategory->id,
            ])
            ->assertSessionHasErrors(['category_id']);

        $this->actingAs($user)
            ->from(route('recurring-operations.edit', $operation))
            ->put(route('recurring-operations.update', $operation), [
                'name' => 'Update with foreign account',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '12.00',
                'account_id' => $foreignAccount->id,
                'category_id' => $ownCategory->id,
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->actingAs($user)
            ->from(route('recurring-operations.edit', $operation))
            ->put(route('recurring-operations.update', $operation), [
                'name' => 'Update with foreign category',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '12.00',
                'account_id' => $ownAccount->id,
                'category_id' => $foreignCategory->id,
            ])
            ->assertSessionHasErrors(['category_id']);

        $operation->refresh();
        $this->assertSame('Owned template', $operation->name);
        $this->assertSame($ownAccount->id, $operation->account_id);
        $this->assertSame($ownCategory->id, $operation->category_id);
    }

    public function test_user_cannot_edit_or_update_another_users_recurring_operation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'type' => Account::TYPE_CARD,
        ]);
        $foreignOperation = RecurringOperation::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Foreign template',
            'type' => RecurringOperation::TYPE_INCOME,
            'amount' => 15_000,
            'account_id' => $otherAccount->id,
            'category_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('recurring-operations.edit', $foreignOperation))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('recurring-operations.update', $foreignOperation), [
                'name' => 'Should not update',
                'type' => RecurringOperation::TYPE_EXPENSE,
                'amount' => '10.00',
                'account_id' => $otherAccount->id,
                'category_id' => null,
            ])
            ->assertForbidden();

        $foreignOperation->refresh();
        $this->assertSame('Foreign template', $foreignOperation->name);
        $this->assertSame(15_000, $foreignOperation->amount);
    }
}

