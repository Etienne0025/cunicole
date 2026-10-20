<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions_synchronisees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('type', 40);
            $table->string('libelle', 200)->nullable();
            $table->string('statut', 20);                   // appliquee ou refusee
            $table->text('message')->nullable();
            $table->string('redirection', 255)->nullable();
            $table->json('payload')->nullable();            // conservé pour les refus : permet de ressaisir
            $table->timestamp('effectuee_le');              // moment réel de la saisie, sur le téléphone
            $table->timestamp('traitee_le');                // moment de l'arrivée sur le serveur
            $table->timestamps();

            $table->index(['statut', 'traitee_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions_synchronisees');
    }
};