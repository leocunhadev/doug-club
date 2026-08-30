<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->index(['mentor_id', 'scheduled_at']);
        });

        Schema::table('mentor_availabilities', function (Blueprint $table) {
            $table->index(['mentor_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->dropIndex(['mentor_id', 'scheduled_at']);
        });

        Schema::table('mentor_availabilities', function (Blueprint $table) {
            $table->dropIndex(['mentor_id', 'day_of_week']);
        });
    }
};
