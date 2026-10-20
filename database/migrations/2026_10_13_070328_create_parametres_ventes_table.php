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
    Schema::create('parametres_ventes', function (Blueprint $table) {
        $table->id();
        $table->decimal('remise_max_pct', 5, 2)->default(0);
        $table->boolean('credit_autorise')->default(false);
        $table->decimal('objectif_hebdomadaire', 14, 2)->nullable();
        $table->decimal('objectif_mensuel', 14, 2)->nullable();
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametres_ventes');
    }
};
