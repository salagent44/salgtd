<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'status',
        'context',
        'waiting_for',
        'waiting_date',
        'tickler_date',
        'notes',
        'sort_order',
        'flagged',
        'completed_at',
        'original_status',
        'goal',
        'project_id',
    ];

    public function tags(): HasMany
    {
        return $this->hasMany(ItemTag::class);
    }

    public function email(): HasOne
    {
        return $this->hasOne(Email::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Item::class, 'project_id');
    }

    protected function casts(): array
    {
        return [
            'waiting_date' => 'date:Y-m-d',
            'tickler_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
            'flagged' => 'boolean',
        ];
    }
}
