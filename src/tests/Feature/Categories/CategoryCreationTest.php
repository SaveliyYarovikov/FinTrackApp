<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_category_create_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('categories.create'))
            ->assertOk();

        $this->assertSame(0, Category::query()->count());
    }

    public function test_user_can_create_a_new_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Utilities',
        ]);

        $response
            ->assertRedirect(route('categories.index', absolute: false))
            ->assertSessionHas('status', 'Category created successfully.');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Utilities',
        ]);
    }

    public function test_category_name_is_required_for_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('categories.create'))
            ->post(route('categories.store'), [
                'name' => '',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('categories', [
            'user_id' => $user->id,
        ]);
    }

    public function test_category_name_must_be_unique_within_same_user_scope_on_creation(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create([
            'name' => 'Transport',
        ]);

        $this->actingAs($user)
            ->from(route('categories.create'))
            ->post(route('categories.store'), [
                'name' => 'Transport',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(
            1,
            Category::query()
                ->where('user_id', $user->id)
                ->where('name', 'Transport')
                ->count()
        );
    }

    public function test_category_name_can_be_reused_by_another_user_on_creation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Category::factory()->for($otherUser)->create([
            'name' => 'Groceries',
        ]);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Groceries',
            ])
            ->assertRedirect(route('categories.index', absolute: false))
            ->assertSessionHas('status', 'Category created successfully.');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Groceries',
        ]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $otherUser->id,
            'name' => 'Groceries',
        ]);
    }

    public function test_guest_cannot_access_category_creation_routes(): void
    {
        $this->get(route('categories.create'))
            ->assertRedirect(route('login', absolute: false));

        $this->post(route('categories.store'), [
            'name' => 'Guest category',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseMissing('categories', [
            'name' => 'Guest category',
        ]);
    }
}
