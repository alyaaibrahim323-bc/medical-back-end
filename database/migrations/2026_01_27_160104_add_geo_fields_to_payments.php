<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('payments', function (Blueprint $t) {
      $t->string('country_code', 2)->nullable()->after('currency');
      $t->string('pricing_region', 20)->nullable()->after('country_code');
      $t->string('client_ip', 45)->nullable()->after('pricing_region');
    });
  }

  public function down(): void {
    Schema::table('payments', function (Blueprint $t) {
      $t->dropColumn(['country_code','pricing_region','client_ip']);
    });
  }
};
