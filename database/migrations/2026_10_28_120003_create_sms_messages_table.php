<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_reservation_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('recipient', 32);
            $table->text('body');
            $table->unsignedBigInteger('cost');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['consumer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
