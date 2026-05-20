<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table): void {
            $table->index(['channel', 'status'], 'communications_channel_status_index');
            $table->index(['status', 'created_at'], 'communications_status_created_at_index');
            $table->index(['notification_template_id', 'status'], 'communications_template_status_index');
        });

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->index(['channel', 'is_active'], 'notification_templates_channel_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table): void {
            $table->dropIndex('communications_channel_status_index');
            $table->dropIndex('communications_status_created_at_index');
            $table->dropIndex('communications_template_status_index');
        });

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropIndex('notification_templates_channel_active_index');
        });
    }
};
