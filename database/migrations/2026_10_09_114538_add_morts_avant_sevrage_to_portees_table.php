<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('portees', function (Blueprint $table) {
        $table->unsignedSmallInteger('morts_avant_sevrage')->default(0)->after('morts_nes');
    });
}

public function down(): void
{
    Schema::table('portees', function (Blueprint $table) {
        $table->dropColumn('morts_avant_sevrage');
    });
}
};
