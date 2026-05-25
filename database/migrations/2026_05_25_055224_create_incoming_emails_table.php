<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_emails', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('subject');
            $table->text('body');
            $table->string('content_hash', 64)->unique();
            $table->string('status')->default('received');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_emails');
    }
};
