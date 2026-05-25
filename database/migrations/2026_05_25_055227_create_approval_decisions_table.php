<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_draft_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('operator_name');
            $table->text('note')->nullable();
            $table->json('override_fields')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
