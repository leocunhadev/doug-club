<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->string('file_url')->nullable()->change();
            $table->string('file_path')->nullable()->after('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropColumn('file_path');
            $table->string('file_url')->nullable(false)->change();
        });
    }
};
