<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'preferred_locale')) {
                $t->string('preferred_locale', 5)->default('en')->after('phone');
            }

            if (!Schema::hasColumn('users', 'device_token')) {
                $t->string('device_token', 255)->nullable()->after('preferred_locale');
            }
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['preferred_locale', 'device_token']);
        });
    }
};
