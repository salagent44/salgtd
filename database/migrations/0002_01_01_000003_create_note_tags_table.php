<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_tags', function (Blueprint $table) {
            $table->id();
            $table->string('note_id');
            $table->string('tag');

            $table->foreign('note_id')->references('id')->on('notes')->onDelete('cascade');
            $table->unique(['note_id', 'tag']);
            $table->index('tag');
            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_tags');
    }
};
