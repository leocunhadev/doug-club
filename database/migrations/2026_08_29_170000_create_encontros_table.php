<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encontros', function (Blueprint $table) {
            $table->id();
            $table->string('tema');
            $table->string('quem');
            $table->dateTime('scheduled_at');
            $table->string('access_url')->nullable();
            $table->foreignId('recording_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encontros');
    }
};
