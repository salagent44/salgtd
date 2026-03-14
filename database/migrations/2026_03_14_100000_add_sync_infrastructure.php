<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global sync cursor — single row tracking latest version
        Schema::create('sync_cursor', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('version')->default(0);
        });

        // Seed with initial row
        DB::table('sync_cursor')->insert(['version' => 0]);

        // Add sync_version and deleted_at to syncable tables
        Schema::table('items', function (Blueprint $table) {
            $table->bigInteger('sync_version')->default(0)->index();
            $table->softDeletes();
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->bigInteger('sync_version')->default(0)->index();
            $table->softDeletes();
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->bigInteger('sync_version')->default(0)->index();
            $table->softDeletes();
        });

        Schema::table('contexts', function (Blueprint $table) {
            $table->bigInteger('sync_version')->default(0)->index();
            $table->softDeletes();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->bigInteger('sync_version')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursor');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['sync_version', 'deleted_at']);
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['sync_version', 'deleted_at']);
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['sync_version', 'deleted_at']);
        });

        Schema::table('contexts', function (Blueprint $table) {
            $table->dropColumn(['sync_version', 'deleted_at']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sync_version', 'updated_at', 'created_at']);
        });
    }
};
