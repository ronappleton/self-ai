<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LegacyPreviewSession extends Model
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
        'persona_name',
        'tone',
        'redactions',
        'message_count',
        'window_count',
        'window_started_at',
        'cooldown_until',
        'last_interaction_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redactions' => 'array',
            'window_started_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'last_interaction_at' => 'datetime',
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
}
