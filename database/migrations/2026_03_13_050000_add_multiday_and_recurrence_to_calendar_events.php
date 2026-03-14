<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('event_date');
            $table->string('end_time')->nullable()->after('event_time');
            $table->string('recurrence')->nullable()->after('color'); // yearly, monthly, weekly, null
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'end_time', 'recurrence']);
        });
    }
};
