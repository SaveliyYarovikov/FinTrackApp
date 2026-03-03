<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        return [
            'user_id' => User::factory(),
            'category_id' => static fn (array $attributes): int => Category::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'limit' => $this->faker->numberBetween(100, 100000),
        ];
    }
}
