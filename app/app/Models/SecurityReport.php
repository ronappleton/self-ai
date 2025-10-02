<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SecurityReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'status',
        'baseline_results',
        'dependency_reports',
        'summary',
        'generated_at',
    ];

    protected $casts = [
        'baseline_results' => 'array',
        'dependency_reports' => 'array',
        'generated_at' => 'datetime',
    ];
}
