<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('email_otps', function (Blueprint $table) {

            /**
             * IMPORTANT:
             * - لازم user_id يبقى عليه index علشان الـ FK
             * - unique يكون على (user_id + purpose)
             */

            // تأكدي إن user_id عليه index عادي
            $table->index('user_id', 'email_otps_user_id_index');

            // unique مركّب (الحل النهائي)
            $table->unique(
                ['user_id', 'purpose'],
                'email_otps_user_purpose_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('email_otps', function (Blueprint $table) {
            // rollback
            $table->dropUnique('email_otps_user_purpose_unique');
            $table->dropIndex('email_otps_user_id_index');
        });
    }
};
