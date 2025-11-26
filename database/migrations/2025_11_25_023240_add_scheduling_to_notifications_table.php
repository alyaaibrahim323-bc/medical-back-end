<?php

// database/migrations/xxxx_add_scheduling_to_notifications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'status')) {
                $table->string('status')->default('draft')->index();
            }

            if (!Schema::hasColumn('notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->index();
            }

            if (!Schema::hasColumn('notifications', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['scheduled_for']);
            // لو status/sent_at كانوا موجودين قبل كدا متشيليهمش في down
        });
    }
};
