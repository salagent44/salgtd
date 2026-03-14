<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Context extends Model
{
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
