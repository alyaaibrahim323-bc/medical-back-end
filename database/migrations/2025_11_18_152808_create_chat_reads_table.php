<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up(): void
{
    Schema::create('chat_reads', function (Blueprint $t) {
        $t->id();

        $t->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
        $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $t->timestamp('read_at')->nullable();

        $t->timestamps();

        $t->unique(['message_id','user_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('chat_reads');
}

};
