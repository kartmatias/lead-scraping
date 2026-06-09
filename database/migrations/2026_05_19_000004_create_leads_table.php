<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_request_id')->constrained('scrape_requests')->onDelete('cascade');
            $table->string('source_type');
            $table->string('source_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->text('address')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('website')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index('scrape_request_id');
            $table->index('email');
            $table->index('cnpj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
