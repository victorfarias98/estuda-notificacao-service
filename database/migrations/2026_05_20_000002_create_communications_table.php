<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient');
            $table->string('channel', 16);
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('origin_system');
            $table->foreignId('notification_template_id')
                ->nullable()
                ->constrained('notification_templates')
                ->nullOnDelete();
            $table->json('variables')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('channel');
            $table->index('origin_system');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
