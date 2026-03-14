<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create('checklist_template_steps', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('checklist_template_id');
            $table->string('title', 500);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('checklist_template_id')
                ->references('id')
                ->on('checklist_templates')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_template_steps');
        Schema::dropIfExists('checklist_templates');
    }
};
