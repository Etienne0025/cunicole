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
    Schema::create('produits_veterinaires', function (Blueprint $table) {
        $table->id();
        $table->string('nom')->unique();
        $table->string('type');
        $table->string('unite')->default('dose');
        $table->decimal('stock_actuel', 10, 2)->default(0);
        $table->decimal('seuil_alerte', 10, 2)->default(0);
        $table->unsignedSmallInteger('delai_attente_jours')->default(0);
        $table->boolean('actif')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits_veterinaires');
    }
};
