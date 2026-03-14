<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->date('event_date');
            $table->string('event_time')->nullable();
            $table->text('description')->default('');
            $table->string('color')->default('blue');
            $table->timestamps();

            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
