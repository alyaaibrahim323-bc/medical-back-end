<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('single_session_offers', function (Blueprint $t) {
      $t->unsignedInteger('price_cents_egp')->default(0)->after('price_cents');
      $t->unsignedInteger('price_cents_usd')->default(0)->after('price_cents_egp');
    });
  }

  public function down(): void {
    Schema::table('single_session_offers', function (Blueprint $t) {
      $t->dropColumn(['price_cents_egp','price_cents_usd']);
    });
  }
};
