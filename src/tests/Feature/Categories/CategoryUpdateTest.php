<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_category_edit_page_for_owned_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Salary',
        ]);

        $this->actingAs($user)
            ->get(route('categories.edit', $category))
            ->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Salary',
        ]);
    }

    public function test_user_can_update_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Old category name',
        ]);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'Updated category name',
        ]);

        $response
            ->assertRedirect(route('categories.index', absolute: false))
            ->assertSessionHas('status', 'Category updated successfully.');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Updated category name',
        ]);
    }

    public function test_user_can_keep_same_category_name_when_updating_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Rent',
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Rent',
            ])
            ->assertRedirect(route('categories.index', absolute: false))
            ->assertSessionHas('status', 'Category updated successfully.');

        $this->assertSame(
            1,
            Category::query()
                ->where('user_id', $user->id)
                ->where('name', 'Rent')
                ->count()
        );
    }

    public function test_user_cannot_rename_category_to_name_used_by_another_own_category(): void
    {
        $user = User::factory()->create();
        $categoryToUpdate = Category::factory()->for($user)->create([
            'name' => 'Travel',
        ]);
        Category::factory()->for($user)->create([
            'name' => 'Food',
        ]);

        $this->actingAs($user)
            ->from(route('categories.edit', $categoryToUpdate))
            ->put(route('categories.update', $categoryToUpdate), [
                'name' => 'Food',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryToUpdate->id,
            'user_id' => $user->id,
            'name' => 'Travel',
        ]);
    }

    public function test_user_can_rename_category_to_name_used_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $categoryToUpdate = Category::factory()->for($user)->create([
            'name' => 'Old name',
        ]);
        Category::factory()->for($otherUser)->create([
            'name' => 'Shared name',
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', $categoryToUpdate), [
                'name' => 'Shared name',
            ])
            ->assertRedirect(route('categories.index', absolute: false))
            ->assertSessionHas('status', 'Category updated successfully.');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryToUpdate->id,
            'user_id' => $user->id,
            'name' => 'Shared name',
        ]);
    }

    public function test_user_cannot_access_edit_page_for_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Foreign category',
        ]);

        $this->actingAs($user)
            ->get(route('categories.edit', $foreignCategory))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $foreignCategory->id,
            'user_id' => $otherUser->id,
            'name' => 'Foreign category',
        ]);
    }

    public function test_user_cannot_update_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Original foreign name',
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', $foreignCategory), [
                'name' => 'Hacked foreign name',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $foreignCategory->id,
            'user_id' => $otherUser->id,
            'name' => 'Original foreign name',
        ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $foreignCategory->id,
            'name' => 'Hacked foreign name',
        ]);
    }

    public function test_guest_cannot_access_category_edit_and_update_routes(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->for($owner)->create([
            'name' => 'Guest protected category',
        ]);

        $this->get(route('categories.edit', $category))
            ->assertRedirect(route('login', absolute: false));

        $this->put(route('categories.update', $category), [
            'name' => 'Guest cannot update',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Guest protected category',
        ]);
    }
}
