<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Note extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'content',
        'pinned',
        'trashed',
        'locked',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'trashed' => 'boolean',
            'locked' => 'boolean',
        ];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(NoteTag::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NoteVersion::class);
    }
}
