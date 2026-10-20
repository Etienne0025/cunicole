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
    Schema::create('paiements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
        $table->decimal('montant', 14, 2);
        $table->string('mode');
        $table->string('operateur')->nullable();
        $table->string('reference')->nullable();
        $table->date('date_paiement');
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();

        $table->index('date_paiement');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
