<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin','doctor','user'])->default('user')->after('password');
            $table->enum('status', ['active','inactive','blocked'])->default('active')->after('role');
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role','status','phone']);
        });
    }
};
