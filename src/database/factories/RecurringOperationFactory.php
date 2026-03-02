<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\RecurringOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringOperation>
 */
class RecurringOperationFactory extends Factory
{
    protected $model = RecurringOperation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'type' => RecurringOperation::TYPE_EXPENSE,
            'amount_minor' => $this->faker->numberBetween(100, 100000),
            'account_id' => Account::factory(),
            'from_account_id' => null,
            'to_account_id' => null,
            'category_id' => null,
            'description' => $this->faker->optional()->sentence(),
            'schedule' => null,
            'interval' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
