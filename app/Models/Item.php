<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes, HasSyncVersion;
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::deleting(function (Item $item) {
            // When a project is deleted, unlink its tasks
            if ($item->status === 'project') {
                Item::where('project_id', $item->id)->update(['project_id' => null]);
            }
        });
    }

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

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
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
