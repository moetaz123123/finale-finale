<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->string('nom_projet');
            $table->string('lien_git');
            $table->timestamps();
        });

        // Supprime la table si elle existe (et désactive les foreign keys)
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('activities');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('projets');

    }
};