<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Promotion extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'build_id',
        'status',
        'status_reason',
        'verifier_id',
        'nonce',
        'signature',
        'request_payload',
        'canary_status',
        'canary_report',
        'rollback_triggered',
        'requested_at',
        'expires_at',
        'promoted_at',
        'rolled_back_at',
        'requested_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'canary_report' => 'array',
            'rollback_triggered' => 'boolean',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'promoted_at' => 'datetime',
            'rolled_back_at' => 'datetime',
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

    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
