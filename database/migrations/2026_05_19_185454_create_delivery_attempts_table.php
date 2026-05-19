<?php

declare(strict_types=1);

use App\Domain\Notification\Enums\NotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_recipients', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('subscriber_id');
            $table->string('status', 30)->default(NotificationStatus::Queued->value);

            /*
             * Provider message id is filled after the notification is accepted
             * by an external gateway mock.
             */
            $table->string('provider_message_id', 120)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dropped_at')->nullable();

            $table->timestamps();

            $table->unique(['notification_id', 'subscriber_id']);

            $table->index('subscriber_id');
            $table->index('status');
            $table->index(['subscriber_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
