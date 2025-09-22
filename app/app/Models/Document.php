<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

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
        'source',
        'type',
        'status',
        'sha256',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'metadata',
        'tags',
        'retention_class',
        'consent_scope',
        'pii_scrubbed',
        'sanitized_content',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'submitted_by',
        'reviewed_by',
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
            'tags' => 'array',
            'pii_scrubbed' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * Get the user that submitted the document.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user that reviewed the document.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get consents associated with the document.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}
