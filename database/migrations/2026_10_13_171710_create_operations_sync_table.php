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
    Schema::create('operations_sync', function (Blueprint $table) {
        $table->id();
        $table->uuid('cle')->unique();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->string('route');
        $table->string('statut');
        $table->string('message', 500)->nullable();
        $table->timestamp('effectue_le');
        $table->timestamp('traitee_le')->nullable();
        $table->timestamps();

        $table->index(['utilisateur_id', 'id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('operations_sync');
}
};
