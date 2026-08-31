<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'total_steps', 'processed_steps', 'new_articles', 'stop_requested', 'message'])]
class ScrapingRun extends Model
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const STOPPED = 'stopped';
    public const FAILED = 'failed';

    protected $casts = [
        'total_steps' => 'integer',
        'processed_steps' => 'integer',
        'new_articles' => 'integer',
        'stop_requested' => 'boolean',
    ];
}