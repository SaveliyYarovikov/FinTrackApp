<?php

declare(strict_types=1);

namespace Tests\Feature\Goals;

use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialGoalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_financial_goal_create_page(): void
    {
        $user = User::factory()->create();

        Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('financial-goals.create'))
            ->assertOk();
    }

    public function test_verified_user_can_open_financial_goal_edit_page_for_own_goal(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoalForUser($user);

        $this->actingAs($user)
            ->get(route('financial-goals.edit', $goal))
            ->assertOk();
    }

    public function test_user_can_create_financial_goal_with_active_status_and_minor_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('financial-goals.store'), [
            'name' => 'Emergency Fund',
            'account_id' => $account->id,
            'amount' => '1234.56',
            'description' => 'For unexpected expenses',
            'target_date' => '2027-01-10',
        ]);

        $response
            ->assertRedirectToRoute('financial-goals.index')
            ->assertSessionHas('status', 'Financial goal created.');

        $this->assertDatabaseHas('financial_goals', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Emergency Fund',
            'description' => 'For unexpected expenses',
            'target_date' => '2027-01-10',
            'target_amount' => 123_456,
            'status' => FinancialGoal::STATUS_ACTIVE,
        ]);
    }

    public function test_user_can_update_own_financial_goal_without_changing_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);
        $goal = FinancialGoal::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Old name',
            'description' => 'Old description',
            'target_amount' => 50_000,
            'target_date' => '2026-09-01',
            'status' => FinancialGoal::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->put(route('financial-goals.update', $goal), [
            'name' => 'Updated name',
            'description' => 'Updated description',
            'amount' => '345.67',
            'target_date' => '2026-12-31',
            'status' => FinancialGoal::STATUS_ACHIEVED,
        ]);

        $response
            ->assertRedirectToRoute('financial-goals.index')
            ->assertSessionHas('status', 'Financial goal updated.');

        $this->assertDatabaseHas('financial_goals', [
            'id' => $goal->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Updated name',
            'description' => 'Updated description',
            'target_amount' => 34_567,
            'target_date' => '2026-12-31',
            'status' => FinancialGoal::STATUS_ACHIEVED,
        ]);
    }

    public function test_financial_goal_creation_fails_with_foreign_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = Account::factory()->for($otherUser)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);

        $this->actingAs($user)
            ->from(route('financial-goals.create'))
            ->post(route('financial-goals.store'), [
                'name' => 'Foreign account goal',
                'account_id' => $foreignAccount->id,
                'amount' => '100.00',
                'description' => 'Should fail',
                'target_date' => '2026-12-31',
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->assertDatabaseMissing('financial_goals', [
            'user_id' => $user->id,
            'name' => 'Foreign account goal',
        ]);
    }

    public function test_financial_goal_creation_fails_with_non_savings_account(): void
    {
        $user = User::factory()->create();
        $nonSavingsAccount = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
            'archived_at' => null,
        ]);

        $this->actingAs($user)
            ->from(route('financial-goals.create'))
            ->post(route('financial-goals.store'), [
                'name' => 'Non savings goal',
                'account_id' => $nonSavingsAccount->id,
                'amount' => '100.00',
                'description' => 'Should fail',
                'target_date' => '2026-12-31',
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->assertDatabaseMissing('financial_goals', [
            'user_id' => $user->id,
            'name' => 'Non savings goal',
        ]);
    }

    public function test_financial_goal_creation_fails_with_archived_savings_account(): void
    {
        $user = User::factory()->create();
        $archivedSavingsAccount = Account::factory()->for($user)->archived()->create([
            'type' => Account::TYPE_SAVINGS,
        ]);

        $this->actingAs($user)
            ->from(route('financial-goals.create'))
            ->post(route('financial-goals.store'), [
                'name' => 'Archived account goal',
                'account_id' => $archivedSavingsAccount->id,
                'amount' => '100.00',
                'description' => 'Should fail',
                'target_date' => '2026-12-31',
            ])
            ->assertSessionHasErrors(['account_id']);

        $this->assertDatabaseMissing('financial_goals', [
            'user_id' => $user->id,
            'name' => 'Archived account goal',
        ]);
    }

    public function test_financial_goal_creation_fails_with_zero_or_negative_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);

        foreach (['0', '-10.50'] as $amount) {
            $this->actingAs($user)
                ->from(route('financial-goals.create'))
                ->post(route('financial-goals.store'), [
                    'name' => 'Invalid amount goal '.$amount,
                    'account_id' => $account->id,
                    'amount' => $amount,
                    'description' => 'Should fail',
                    'target_date' => '2026-12-31',
                ])
                ->assertSessionHasErrors(['amount']);
        }

        $this->assertSame(0, FinancialGoal::query()->count());
    }

    public function test_financial_goal_update_rejects_account_id_in_payload(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);
        $anotherAccount = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);
        $goal = FinancialGoal::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Existing goal',
            'description' => 'Existing description',
            'target_amount' => 12_500,
            'target_date' => '2026-11-15',
            'status' => FinancialGoal::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->from(route('financial-goals.edit', $goal))
            ->put(route('financial-goals.update', $goal), [
                'name' => 'Attempted account change',
                'description' => 'Description',
                'amount' => '50.00',
                'target_date' => '2026-12-20',
                'status' => FinancialGoal::STATUS_ACTIVE,
                'account_id' => $anotherAccount->id,
            ])
            ->assertSessionHasErrors(['account_id']);

        $goal->refresh();
        $this->assertSame($account->id, $goal->account_id);
        $this->assertSame('Existing goal', $goal->name);
    }

    public function test_user_cannot_edit_or_update_another_users_financial_goal(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignGoal = $this->createGoalForUser($otherUser, [
            'name' => 'Foreign goal',
            'target_amount' => 99_900,
            'status' => FinancialGoal::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('financial-goals.edit', $foreignGoal))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('financial-goals.update', $foreignGoal), [
                'name' => 'Should not update',
                'description' => 'Should not update',
                'amount' => '10.00',
                'target_date' => '2026-12-31',
                'status' => FinancialGoal::STATUS_ACHIEVED,
            ])
            ->assertForbidden();

        $foreignGoal->refresh();
        $this->assertSame('Foreign goal', $foreignGoal->name);
        $this->assertSame(99_900, $foreignGoal->target_amount);
        $this->assertSame(FinancialGoal::STATUS_ACTIVE, $foreignGoal->status);
    }

    public function test_guest_cannot_access_financial_goal_create_store_edit_or_update_routes(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_SAVINGS,
            'archived_at' => null,
        ]);
        $goal = $this->createGoalForUser($user, ['account_id' => $account->id]);

        $this->get(route('financial-goals.create'))
            ->assertRedirect(route('login', absolute: false));

        $this->post(route('financial-goals.store'), [
            'name' => 'Guest goal',
            'account_id' => $account->id,
            'amount' => '100.00',
            'description' => 'Guest should fail',
            'target_date' => '2026-12-31',
        ])->assertRedirect(route('login', absolute: false));

        $this->get(route('financial-goals.edit', $goal))
            ->assertRedirect(route('login', absolute: false));

        $this->put(route('financial-goals.update', $goal), [
            'name' => 'Guest update',
            'description' => 'Guest update',
            'amount' => '200.00',
            'target_date' => '2026-12-31',
            'status' => FinancialGoal::STATUS_ACHIEVED,
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseMissing('financial_goals', [
            'name' => 'Guest goal',
        ]);

        $goal->refresh();
        $this->assertNotSame('Guest update', $goal->name);
    }

    private function createGoalForUser(User $user, array $overrides = []): FinancialGoal
    {
        if (! array_key_exists('account_id', $overrides)) {
            $account = Account::factory()->for($user)->create([
                'type' => Account::TYPE_SAVINGS,
                'archived_at' => null,
            ]);
            $overrides['account_id'] = $account->id;
        }

        return FinancialGoal::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Test goal',
            'description' => 'Goal description',
            'target_amount' => 150_000,
            'target_date' => '2026-12-31',
            'status' => FinancialGoal::STATUS_ACTIVE,
        ], $overrides));
    }
}
