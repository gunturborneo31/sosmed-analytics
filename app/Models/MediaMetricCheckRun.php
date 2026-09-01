<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'status',
    'total_sources',
    'processed_sources',
    'view_readable_sources',
    'visitor_readable_sources',
    'stop_requested',
    'message',
])]
class MediaMetricCheckRun extends Model
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const STOPPED = 'stopped';

    protected $casts = [
        'total_sources' => 'integer',
        'processed_sources' => 'integer',
        'view_readable_sources' => 'integer',
        'visitor_readable_sources' => 'integer',
        'stop_requested' => 'boolean',
    ];
}
