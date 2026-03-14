<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('goal')->nullable()->after('notes');
            $table->string('project_id')->nullable()->after('goal');

            $table->foreign('project_id')
                ->references('id')
                ->on('items')
                ->nullOnDelete();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn(['goal', 'project_id']);
        });
    }
};
