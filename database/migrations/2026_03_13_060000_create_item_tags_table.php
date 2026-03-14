<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_tags', function (Blueprint $table) {
            $table->id();
            $table->string('item_id');
            $table->string('tag');

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->unique(['item_id', 'tag']);
            $table->index('tag');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_tags');
    }
};
