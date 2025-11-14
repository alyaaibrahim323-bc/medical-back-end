<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('package_redemptions', function (Blueprint $t) {
            // user_package_id
            if (!Schema::hasColumn('package_redemptions','user_package_id')) {
                $t->foreignId('user_package_id')
                  ->after('id')
                  ->constrained('user_packages')
                  ->cascadeOnDelete();
            }

            // therapy_session_id
            if (!Schema::hasColumn('package_redemptions','therapy_session_id')) {
                $t->foreignId('therapy_session_id')
                  ->nullable()
                  ->after('user_package_id')
                  ->constrained('therapy_sessions')
                  ->nullOnDelete();
            }

            // redeemed_at
            if (!Schema::hasColumn('package_redemptions','redeemed_at')) {
                $t->timestamp('redeemed_at')->nullable()->after('therapy_session_id');
            }

            // notes
            if (!Schema::hasColumn('package_redemptions','notes')) {
                $t->text('notes')->nullable()->after('redeemed_at');
            }

            // index للمساعدة في التقارير
            $t->index(['user_package_id','redeemed_at'], 'pkg_redemptions_userpkg_redeemed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('package_redemptions', function (Blueprint $t) {
            // فك الـ index
            try { $t->dropIndex('pkg_redemptions_userpkg_redeemed_idx'); } catch (\Throwable $e) {}

            // اسقاط الأعمدة (مع المفاتيح)
            if (Schema::hasColumn('package_redemptions','therapy_session_id')) {
                $t->dropConstrainedForeignId('therapy_session_id');
            }
            if (Schema::hasColumn('package_redemptions','user_package_id')) {
                $t->dropConstrainedForeignId('user_package_id');
            }
            if (Schema::hasColumn('package_redemptions','redeemed_at')) {
                $t->dropColumn('redeemed_at');
            }
            if (Schema::hasColumn('package_redemptions','notes')) {
                $t->dropColumn('notes');
            }
        });
    }
};
