<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up(): void
{
    Schema::create('chat_messages', function (Blueprint $t) {
        $t->id();

        $t->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
        $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

        $t->enum('sender_role', ['client','therapist','admin','system']);

        $t->enum('type', ['text','image','audio','file','system'])->default('text');
        $t->text('body')->nullable();

        $t->string('attachment_path')->nullable();
        $t->unsignedInteger('duration_ms')->nullable();

        $t->foreignId('replied_to_id')->nullable()
          ->constrained('chat_messages')->nullOnDelete();

        $t->timestamps();

        $t->index(['chat_id','created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('chat_messages');
}

};
