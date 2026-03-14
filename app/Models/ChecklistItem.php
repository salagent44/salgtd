<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistItem extends Model
{
    use HasFactory, SoftDeletes, HasSyncVersion;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'item_id',
        'title',
        'completed',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
