<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Build extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'rfc_id',
        'status',
        'status_reason',
        'diff_disk',
        'diff_path',
        'test_report_disk',
        'test_report_path',
        'artefacts_disk',
        'artefacts_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->getKey()) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function rfc(): BelongsTo
    {
        return $this->belongsTo(RfcProposal::class, 'rfc_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function diffUrl(): ?string
    {
        return $this->makeStorageUrl($this->diff_disk, $this->diff_path);
    }

    public function testReportUrl(): ?string
    {
        return $this->makeStorageUrl($this->test_report_disk, $this->test_report_path);
    }

    public function artefactsUrl(): ?string
    {
        return $this->makeStorageUrl($this->artefacts_disk, $this->artefacts_path);
    }

    private function makeStorageUrl(?string $disk, ?string $path): ?string
    {
        if (! $disk || ! $path) {
            return null;
        }

        return sprintf('%s://%s', $disk, ltrim($path, '/'));
    }
}
