<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('status')->default('inbox');
            $table->string('context')->nullable();
            $table->string('waiting_for')->nullable();
            $table->date('waiting_date')->nullable();
            $table->date('tickler_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
