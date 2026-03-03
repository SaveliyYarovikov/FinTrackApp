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
            'amount' => $this->faker->numberBetween(100, 100000),
            'account_id' => Account::factory(),
            'category_id' => null,
        ];
    }
}
