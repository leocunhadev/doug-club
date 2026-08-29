<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('category', ['Encontros', 'Convidados', 'Frameworks'])->default('Encontros')->after('course_id');
            $table->enum('tier', ['start', 'club'])->default('start')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['category', 'tier']);
        });
    }
};
