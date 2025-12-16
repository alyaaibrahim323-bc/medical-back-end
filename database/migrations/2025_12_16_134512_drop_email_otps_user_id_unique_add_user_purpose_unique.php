<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop FK first (عشان MySQL ساعات يربط الـ index بالـ FK)
        Schema::table('email_otps', function (Blueprint $table) {
            try { $table->dropForeign(['user_id']); } catch (\Throwable $e) {}
        });
        // 2) Drop the old UNIQUE(user_id)
        Schema::table('email_otps', callback: function (Blueprint $table) {
            try { $table->dropUnique('email_otps_user_id_index'); } catch (\Throwable $e) {}
        });
        Schema::table('email_otps', callback: function (Blueprint $table) {
            try { $table->dropUnique('email_otps_user_purpose_unique'); } catch (\Throwable $e) {}
        });

        // 4) Add composite UNIQUE(user_id, purpose)
        Schema::table('email_otps', function (Blueprint $table) {
            try { $table->unique(['user_id','purpose'], 'email_otps_user_purpose_unique'); } catch (\Throwable $e) {}
        });

        // 5) Re-add FK
        Schema::table('email_otps', function (Blueprint $table) {
            try {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('email_otps', function (Blueprint $table) {
            try { $table->dropForeign(['user_id']); } catch (\Throwable $e) {}
        });

        Schema::table('email_otps', function (Blueprint $table) {
            try { $table->dropUnique('email_otps_user_purpose_unique'); } catch (\Throwable $e) {}
            try { $table->dropIndex('email_otps_user_id_index'); } catch (\Throwable $e) {}
        });

        Schema::table('email_otps', function (Blueprint $table) {
            // رجّع UNIQUE(user_id)
            $table->unique('user_id', 'email_otps_user_id_unique');
        });

        Schema::table('email_otps', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
