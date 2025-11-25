<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('notification_deliveries', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('notification_id');
        $table->unsignedBigInteger('user_id');

        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('read_at')->nullable();

        $table->timestamps();

        $table->unique(['notification_id', 'user_id']);

        $table->foreign('notification_id')
            ->references('id')->on('notifications')->cascadeOnDelete();

        $table->foreign('user_id')
            ->references('id')->on('users')->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::dropIfExists('notification_deliveries');
}

};
