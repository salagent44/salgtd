<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->default('');
            $table->text('content')->default('');
            $table->boolean('pinned')->default(false);
            $table->boolean('trashed')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->index('trashed');
            $table->index('pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
