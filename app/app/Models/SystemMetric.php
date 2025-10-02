<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model
{
    protected $fillable = [
        'collected_at',
        'metrics',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'metrics' => 'array',
    ];
}
