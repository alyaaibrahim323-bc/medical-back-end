<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('notification_settings', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('user_id')->unique();

        $table->boolean('general')->default(true);
        $table->boolean('session')->default(true);
        $table->boolean('rating')->default(true);
        $table->boolean('security')->default(true);
        $table->boolean('system')->default(true);

        $table->timestamps();

        $table->foreign('user_id')
            ->references('id')->on('users')->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::dropIfExists('notification_settings');
}

};
