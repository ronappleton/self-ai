<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BackupSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'component',
        'status',
        'rotation_tier',
        'snapshot_path',
        'restore_verified_at',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'restore_verified_at' => 'datetime',
    ];
}
