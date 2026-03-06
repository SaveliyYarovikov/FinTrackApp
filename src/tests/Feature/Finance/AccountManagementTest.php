<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_account_create_and_edit_pages(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => Account::TYPE_CARD,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('accounts.edit', $account))
            ->assertOk();
    }

    public function test_user_can_create_account_with_supported_type_and_minor_balance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Primary account',
            'currency' => 'usd',
            'type' => 'CARD',
            'balance' => '123.45',
        ]);

        $response
            ->assertRedirect(route('accounts.index', absolute: false))
            ->assertSessionHas('status', 'Account created successfully.');

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Primary account',
            'currency' => 'USD',
            'type' => Account::TYPE_CARD,
            'balance' => 12_345,
        ]);
    }

    public function test_user_can_update_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Old name',
            'type' => Account::TYPE_CARD,
            'balance' => 1_000,
        ]);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Savings account',
            'type' => Account::TYPE_SAVINGS,
            'balance' => '250.75',
        ]);

        $response
            ->assertRedirect(route('accounts.index', absolute: false))
            ->assertSessionHas('status', 'Account updated successfully.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'user_id' => $user->id,
            'name' => 'Savings account',
            'type' => Account::TYPE_SAVINGS,
            'balance' => 25_075,
        ]);
    }

    public function test_account_name_must_be_unique_per_user_but_can_repeat_for_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'Shared name',
            'type' => Account::TYPE_CARD,
        ]);

        $this->actingAs($user)
            ->from(route('accounts.create'))
            ->post(route('accounts.store'), [
                'name' => 'Shared name',
                'currency' => 'EUR',
                'type' => Account::TYPE_CARD,
                'balance' => '10.00',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(1, Account::query()->where('user_id', $user->id)->count());

        $this->actingAs($otherUser)
            ->post(route('accounts.store'), [
                'name' => 'Shared name',
                'currency' => 'EUR',
                'type' => Account::TYPE_CARD,
                'balance' => '15.00',
            ])
            ->assertRedirect(route('accounts.index', absolute: false))
            ->assertSessionHas('status', 'Account created successfully.');

        $this->assertDatabaseHas('accounts', [
            'user_id' => $otherUser->id,
            'name' => 'Shared name',
            'type' => Account::TYPE_CARD,
            'balance' => 1_500,
        ]);
    }

    public function test_user_cannot_edit_or_update_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = Account::factory()->for($otherUser)->create([
            'name' => 'Foreign account',
            'type' => Account::TYPE_CARD,
            'balance' => 8_000,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.edit', $foreignAccount))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('accounts.update', $foreignAccount), [
                'name' => 'Hacked name',
                'type' => Account::TYPE_SAVINGS,
                'balance' => '99.99',
            ])
            ->assertForbidden();

        $foreignAccount->refresh();
        $this->assertSame('Foreign account', $foreignAccount->name);
        $this->assertSame(Account::TYPE_CARD, $foreignAccount->type);
        $this->assertSame(8_000, $foreignAccount->balance);
    }
}

