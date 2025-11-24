<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('chats', function (Blueprint $t) {
        $t->id();

        $t->foreignId('therapy_session_id')
          ->constrained('therapy_sessions')
          ->cascadeOnDelete();

        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();

        $t->enum('status', ['pending','replied','closed'])->default('pending');

        $t->timestamp('last_message_at')->nullable();
        $t->timestamp('last_client_message_at')->nullable();
        $t->timestamp('last_therapist_message_at')->nullable();

        $t->foreignId('assigned_by')->nullable()
          ->constrained('users')->nullOnDelete();
        $t->timestamp('assigned_at')->nullable();

        $t->timestamps();

        $t->unique('therapy_session_id');
        $t->index(['status','last_message_at']);
        $t->index(['therapist_id','status']);
    });
}

public function down(): void
{
    Schema::dropIfExists('chats');
}

};
