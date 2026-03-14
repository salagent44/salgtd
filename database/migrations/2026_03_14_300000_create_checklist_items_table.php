<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('item_id');
            $table->string('title', 500);
            $table->boolean('completed')->default(false);
            $table->integer('sort_order')->default(0);
            $table->bigInteger('sync_version')->default(0)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
