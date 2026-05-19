<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_attempts', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('notification_recipient_id')
                ->constrained('notification_recipients')
                ->cascadeOnDelete();

            $table->unsignedInteger('attempt_number');
            $table->string('provider', 50);
            $table->string('status', 30);

            /*
             * Error message is stored only for failed attempts.
             * It helps to debug provider errors and retry behaviour.
             */
            $table->text('error_message')->nullable();

            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index('notification_recipient_id');
            $table->index('status');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
