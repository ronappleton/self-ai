<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LegacyDirective extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'beneficiaries',
        'topics_allow',
        'topics_deny',
        'duration',
        'rate_limits',
        'unlock_policy',
        'passphrase_hash',
        'panic_disabled_at',
        'panic_disabled_reason',
        'erased_at',
        'erased_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'beneficiaries' => 'array',
            'topics_allow' => 'array',
            'topics_deny' => 'array',
            'duration' => 'array',
            'rate_limits' => 'array',
            'unlock_policy' => 'array',
            'panic_disabled_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->getKey()) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(LegacyDirectiveUnlock::class, 'directive_id');
    }

    public function isPanicDisabled(): bool
    {
        return $this->panic_disabled_at !== null;
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }
}
