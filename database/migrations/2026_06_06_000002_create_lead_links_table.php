<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->enum('match_method', ['phone', 'website', 'cnpj', 'email', 'name_fuzzy', 'new']);
            $table->unsignedTinyInteger('match_score')->default(100); // 0-100
            $table->unique('lead_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_links');
    }
};
