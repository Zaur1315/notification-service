<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', static function (Blueprint $table): void {
            $table->id();

            $table->string('channel', 20);
            $table->string('priority', 30);
            $table->text('message');

            /*
             * External request identifier used to protect the system from duplicate
             * notification creation when the caller retries the same API request.
             */
            $table->string('idempotency_key', 120)->nullable()->unique();

            $table->timestamps();

            $table->index('channel');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
