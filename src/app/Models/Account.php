<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyMinorCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_CARD = 'card';
    public const TYPE_BANK = 'bank';
    public const TYPE_SAVINGS = 'savings';

    protected $fillable = [
        'user_id',
        'name',
        'currency',
        'type',
        'balance',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => MoneyMinorCast::class,
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
