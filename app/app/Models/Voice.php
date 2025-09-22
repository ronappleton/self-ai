<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Voice extends Model
{
    use HasFactory;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'voice_id',
        'status',
        'storage_disk',
        'dataset_path',
        'dataset_sha256',
        'sample_count',
        'script_version',
        'consent_scope',
        'metadata',
        'enrolled_at',
        'enrolled_by',
        'disabled_at',
        'disabled_reason',
        'disabled_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'enrolled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->getKey()) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }
        });
    }

    /**
     * User who enrolled the voice.
     */
    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * User who disabled the voice.
     */
    public function disabler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }
}
