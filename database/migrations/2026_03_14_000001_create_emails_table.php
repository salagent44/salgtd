<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('item_id')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('to_address');
            $table->string('subject');
            $table->text('body_text');
            $table->timestamp('received_at');
            $table->string('message_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
