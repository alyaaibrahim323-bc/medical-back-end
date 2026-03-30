<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->string('country_code', 2)->nullable()->after('email');
      $t->string('pricing_region', 20)->nullable()->after('country_code'); // EG_LOCAL / INTL
      $t->timestamp('geo_detected_at')->nullable()->after('pricing_region');
    });
  }

  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropColumn(['country_code','pricing_region','geo_detected_at']);
    });
  }
};
