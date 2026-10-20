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
    Schema::create('pesees_lots', function (Blueprint $table) {
        $table->id();
        $table->foreignId('lot_id')->constrained('lots_engraissement')->cascadeOnDelete();
        $table->date('date_pesee');
        $table->decimal('poids_moyen_kg', 5, 2);
        $table->unsignedSmallInteger('nombre_pese');
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('pesees_lots');
}
};
