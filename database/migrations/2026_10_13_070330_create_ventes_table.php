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
    Schema::create('ventes', function (Blueprint $table) {
        $table->id();
        $table->string('numero')->unique();
        $table->string('jeton', 64)->nullable()->unique();   // protège contre le double envoi du formulaire
        $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        $table->date('date_vente');
        $table->decimal('total_brut', 14, 2);
        $table->decimal('remise_pct', 5, 2)->default(0);
        $table->decimal('remise_montant', 14, 2)->default(0);
        $table->decimal('total_net', 14, 2);
        $table->decimal('montant_paye', 14, 2)->default(0);
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index('date_vente');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
