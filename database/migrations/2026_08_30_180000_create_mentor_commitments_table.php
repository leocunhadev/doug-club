<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->timestamps();
            $table->unique('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_commitments');
    }
};
