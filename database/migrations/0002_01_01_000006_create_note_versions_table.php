<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_versions', function (Blueprint $table) {
            $table->id();
            $table->string('note_id');
            $table->string('title')->default('');
            $table->text('content')->default('');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('note_id')->references('id')->on('notes')->onDelete('cascade');
            $table->index('note_id');
            $table->index(['note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_versions');
    }
};
