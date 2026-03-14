<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes, HasSyncVersion;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'event_date',
        'end_date',
        'event_time',
        'end_time',
        'description',
        'color',
        'recurrence',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }
}
