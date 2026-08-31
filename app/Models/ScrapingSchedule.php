<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['frequency_minutes', 'is_active'])]
class ScrapingSchedule extends Model
{
    protected $casts = ['frequency_minutes' => 'integer', 'is_active' => 'boolean'];
}