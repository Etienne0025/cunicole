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
    Schema::create('portees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('saillie_id')->unique()->constrained('saillies')->restrictOnDelete();
        $table->date('date_mise_bas');
        $table->unsignedSmallInteger('nes_vivants');
        $table->unsignedSmallInteger('morts_nes')->default(0);
        $table->date('date_sevrage')->nullable();
        $table->unsignedSmallInteger('sevres')->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portees');
    }
};
