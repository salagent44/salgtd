<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'note_id',
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
