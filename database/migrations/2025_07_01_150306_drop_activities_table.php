<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprime la table si elle existe (et désactive les foreign keys)
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('activities');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Tu peux laisser vide ou remettre la structure si besoin
    }
};