<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'note_id',
        'tag',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
