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
    Schema::create('clotures_journees', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamp('cloturee_le');
        $table->unsignedSmallInteger('total');
        $table->unsignedSmallInteger('faites');
        $table->unsignedSmallInteger('non_faites');
        $table->text('commentaire')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('clotures_journees');
}
};
