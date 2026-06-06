<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_requests', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['google_maps', 'instagram', 'linkedin', 'cnpj']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->json('filters')->nullable();
            $table->string('apify_run_id')->nullable();
            $table->string('apify_dataset_id')->nullable();
            $table->unsignedInteger('total_leads')->default(0);
            $table->unsignedInteger('completed_leads')->default(0);
            $table->unsignedInteger('failed_leads')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_requests');
    }
};
