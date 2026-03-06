<?php

declare(strict_types=1);

namespace Tests\Feature\Budgets;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_budget_create_page(): void
    {
        $user = User::factory()->create();

        Category::factory()->for($user)->create([
            'name' => 'Food',
        ]);

        $this->actingAs($user)
            ->get(route('budgets.create'))
            ->assertOk();
    }

    public function test_verified_user_can_open_budget_edit_page_for_own_budget(): void
    {
        $user = User::factory()->create();
        $budget = $this->createBudgetForUser($user);

        $this->actingAs($user)
            ->get(route('budgets.edit', $budget))
            ->assertOk();
    }

    public function test_user_can_create_budget_with_minor_limit_value(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Transport',
        ]);

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'amount' => '250.40',
        ]);

        $response
            ->assertRedirectToRoute('budgets.index')
            ->assertSessionHas('status', 'Budget created.');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'limit' => 25_040,
        ]);
    }

    public function test_user_can_update_own_budget_and_change_category_period_and_limit(): void
    {
        $user = User::factory()->create();
        $oldCategory = Category::factory()->for($user)->create([
            'name' => 'Old category',
        ]);
        $newCategory = Category::factory()->for($user)->create([
            'name' => 'New category',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'category_id' => $oldCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'limit' => 10_000,
        ]);

        $response = $this->actingAs($user)->put(route('budgets.update', $budget), [
            'category_id' => $newCategory->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'amount' => '399.99',
        ]);

        $response
            ->assertRedirectToRoute('budgets.index')
            ->assertSessionHas('status', 'Budget updated.');

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'user_id' => $user->id,
            'category_id' => $newCategory->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'limit' => 39_999,
        ]);
    }

    public function test_budget_creation_fails_with_foreign_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Foreign category',
        ]);

        $this->actingAs($user)
            ->from(route('budgets.create'))
            ->post(route('budgets.store'), [
                'category_id' => $foreignCategory->id,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'amount' => '100.00',
            ])
            ->assertSessionHasErrors(['category_id']);

        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);
    }

    public function test_budget_update_fails_with_foreign_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownCategory = Category::factory()->for($user)->create([
            'name' => 'Own category',
        ]);
        $foreignCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Foreign category',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'category_id' => $ownCategory->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'limit' => 12_000,
        ]);

        $this->actingAs($user)
            ->from(route('budgets.edit', $budget))
            ->put(route('budgets.update', $budget), [
                'category_id' => $foreignCategory->id,
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-30',
                'amount' => '150.00',
            ])
            ->assertSessionHasErrors(['category_id']);

        $budget->refresh();
        $this->assertSame($ownCategory->id, $budget->category_id);
        $this->assertSame(12_000, $budget->limit);
    }

    public function test_budget_creation_fails_when_period_end_is_before_period_start(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budgets.create'))
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'period_start' => '2026-07-20',
                'period_end' => '2026-07-01',
                'amount' => '120.00',
            ])
            ->assertSessionHasErrors(['period_end']);

        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-07-20',
            'period_end' => '2026-07-01',
        ]);
    }

    public function test_budget_creation_fails_with_zero_or_negative_amount(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        foreach (['0', '-1.00'] as $amount) {
            $this->actingAs($user)
                ->from(route('budgets.create'))
                ->post(route('budgets.store'), [
                    'category_id' => $category->id,
                    'period_start' => '2026-08-01',
                    'period_end' => '2026-08-31',
                    'amount' => $amount,
                ])
                ->assertSessionHasErrors(['amount']);
        }

        $this->assertSame(0, Budget::query()->count());
    }

    public function test_budget_creation_fails_for_duplicate_user_category_and_period_combination(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'limit' => 50_000,
        ]);

        $this->actingAs($user)
            ->from(route('budgets.create'))
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'amount' => '700.00',
            ])
            ->assertSessionHasErrors(['category_id']);

        $this->assertSame(
            1,
            Budget::query()
                ->where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->where('period_start', '2026-09-01')
                ->where('period_end', '2026-09-30')
                ->count()
        );
    }

    public function test_same_logical_period_can_exist_for_another_user_with_own_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userCategory = Category::factory()->for($user)->create([
            'name' => 'Rent',
        ]);
        $otherUserCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Rent',
        ]);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'category_id' => $userCategory->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
                'amount' => '500.00',
            ])
            ->assertRedirectToRoute('budgets.index')
            ->assertSessionHas('status', 'Budget created.');

        $this->actingAs($otherUser)
            ->post(route('budgets.store'), [
                'category_id' => $otherUserCategory->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
                'amount' => '500.00',
            ])
            ->assertRedirectToRoute('budgets.index')
            ->assertSessionHas('status', 'Budget created.');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $userCategory->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'limit' => 50_000,
        ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $otherUser->id,
            'category_id' => $otherUserCategory->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'limit' => 50_000,
        ]);
    }

    public function test_user_can_update_budget_with_same_values_without_false_unique_conflict(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'limit' => 33_300,
        ]);

        $this->actingAs($user)
            ->put(route('budgets.update', $budget), [
                'category_id' => $category->id,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
                'amount' => '333.00',
            ])
            ->assertRedirectToRoute('budgets.index')
            ->assertSessionHas('status', 'Budget updated.');

        $this->assertSame(
            1,
            Budget::query()
                ->where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->where('period_start', '2026-11-01')
                ->where('period_end', '2026-11-30')
                ->count()
        );

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'limit' => 33_300,
        ]);
    }

    public function test_user_cannot_edit_or_update_another_users_budget(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userCategory = Category::factory()->for($user)->create();
        $foreignBudget = $this->createBudgetForUser($otherUser, [
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'limit' => 45_000,
        ]);

        $this->actingAs($user)
            ->get(route('budgets.edit', $foreignBudget))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('budgets.update', $foreignBudget), [
                'category_id' => $userCategory->id,
                'period_start' => '2026-12-01',
                'period_end' => '2026-12-31',
                'amount' => '100.00',
            ])
            ->assertForbidden();

        $foreignBudget->refresh();
        $this->assertSame(45_000, $foreignBudget->limit);
    }

    public function test_guest_cannot_access_budget_create_store_edit_or_update_routes(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'limit' => 20_000,
        ]);

        $this->get(route('budgets.create'))
            ->assertRedirect(route('login', absolute: false));

        $this->post(route('budgets.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'amount' => '200.00',
        ])->assertRedirect(route('login', absolute: false));

        $this->get(route('budgets.edit', $budget))
            ->assertRedirect(route('login', absolute: false));

        $this->put(route('budgets.update', $budget), [
            'category_id' => $category->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'amount' => '300.00',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertSame(1, Budget::query()->count());
        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'limit' => 30_000,
        ]);
    }

    private function createBudgetForUser(User $user, array $overrides = []): Budget
    {
        if (! array_key_exists('category_id', $overrides)) {
            $category = Category::factory()->for($user)->create();
            $overrides['category_id'] = $category->id;
        }

        return Budget::query()->create(array_merge([
            'user_id' => $user->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'limit' => 15_000,
        ], $overrides));
    }
}
