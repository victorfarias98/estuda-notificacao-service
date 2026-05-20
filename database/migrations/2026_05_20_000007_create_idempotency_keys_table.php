<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 255)->unique();
            $table->string('origin_system', 120)->nullable();
            $table->string('request_hash', 64);
            $table->foreignId('communication_id')
                ->nullable()
                ->constrained('communications')
                ->nullOnDelete();
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
