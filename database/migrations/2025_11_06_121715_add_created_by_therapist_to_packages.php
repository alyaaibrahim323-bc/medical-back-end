<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('packages', function (Blueprint $t) {
      $t->foreignId('created_by_therapist_id')
        ->nullable()
        ->constrained('therapists')
        ->nullOnDelete()
        ->after('applicability');
      $t->index(['applicability','created_by_therapist_id']);
    });
  }
  public function down(): void {
    Schema::table('packages', function (Blueprint $t) {
      $t->dropConstrainedForeignId('created_by_therapist_id');
      $t->dropIndex(['packages_applicability_created_by_therapist_id_index']);
    });
  }
};
