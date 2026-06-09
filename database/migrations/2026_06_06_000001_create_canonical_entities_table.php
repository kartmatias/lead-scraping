<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('cnpj')->nullable()->unique();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('website')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_entities');
    }
};
