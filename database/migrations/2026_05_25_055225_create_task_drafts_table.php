<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('summary');
            $table->string('priority');
            $table->string('suggested_project')->nullable();
            $table->string('suggested_team')->nullable();
            $table->decimal('confidence', 5, 4);
            $table->json('missing_information')->nullable();
            $table->text('suggested_next_action')->nullable();
            $table->string('status')->default('pending_review');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_drafts');
    }
};
