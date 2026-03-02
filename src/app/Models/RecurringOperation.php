<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyMinorCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOperation extends Model
{
    use HasFactory;

    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_TRANSFER = 'transfer';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'amount_minor',
        'account_id',
        'from_account_id',
        'to_account_id',
        'category_id',
        'description',
        'schedule',
        'interval',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyMinorCast::class,
            'interval' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
