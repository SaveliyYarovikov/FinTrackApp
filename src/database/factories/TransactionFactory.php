<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => static fn (array $attributes): int => (int) Account::query()->find($attributes['account_id'])->user_id,
            'category_id' => null,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => -$this->faker->numberBetween(100, 100000),
            'description' => $this->faker->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => Transaction::TYPE_INCOME,
            'amount' => $this->faker->numberBetween(100, 100000),
        ]);
    }
}
