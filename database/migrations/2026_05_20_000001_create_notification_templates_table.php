<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('channel', 16);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['slug', 'channel']);
            $table->index('channel');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
