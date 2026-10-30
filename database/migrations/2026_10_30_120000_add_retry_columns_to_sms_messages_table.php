<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_retry_at')->nullable()->after('attempts');
            $table->string('last_error', 500)->nullable()->after('next_retry_at');

            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropColumn(['attempts', 'next_retry_at', 'last_error']);
        });
    }
};
