<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $attributes = [
            'user_id' => User::factory(),
            'name' => $this->faker->unique()->word(),
        ];

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'type')) {
            $attributes['type'] = Category::TYPE_EXPENSE;
        }

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'is_system')) {
            $attributes['is_system'] = false;
        }

        return $attributes;
    }
}
