<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('prompt_version')->default('v1');
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
