<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Category extends Model
{
    use HasFactory;

    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    private static ?bool $typeColumnExists = null;

    private static ?bool $isSystemColumnExists = null;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        if (! self::supportsIsSystemColumn()) {
            return $query->where('user_id', $userId);
        }

        return $query->where(function (Builder $innerQuery) use ($userId): void {
            $innerQuery
                ->where('user_id', $userId)
                ->orWhere(function (Builder $systemQuery): void {
                    $systemQuery
                        ->whereNull('user_id')
                        ->where('is_system', true);
                });
        });
    }

    public static function supportsTypeColumn(): bool
    {
        if (self::$typeColumnExists === null) {
            self::$typeColumnExists = Schema::hasTable('categories')
                && Schema::hasColumn('categories', 'type');
        }

        return self::$typeColumnExists;
    }

    public static function supportsIsSystemColumn(): bool
    {
        if (self::$isSystemColumnExists === null) {
            self::$isSystemColumnExists = Schema::hasTable('categories')
                && Schema::hasColumn('categories', 'is_system');
        }

        return self::$isSystemColumnExists;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
