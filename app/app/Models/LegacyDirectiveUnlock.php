<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyDirectiveUnlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'directive_id',
        'executor_name',
        'status',
        'reason',
        'requested_at',
        'available_after',
        'approved_at',
        'denied_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'available_after' => 'datetime',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
        ];
    }

    public function directive(): BelongsTo
    {
        return $this->belongsTo(LegacyDirective::class, 'directive_id');
    }
}
