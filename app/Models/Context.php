<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Context extends Model
{
    use SoftDeletes, HasSyncVersion;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'built_in',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'built_in' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
