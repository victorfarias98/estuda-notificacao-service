<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table): void {
            $table->string('correlation_id', 36)->nullable()->after('origin_system');
            $table->softDeletes();

            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropSoftDeletes();
            $table->dropColumn('correlation_id');
        });
    }
};
