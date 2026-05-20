<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use Illuminate\Console\Command;

final class SetupRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Declare RabbitMQ exchange and notification queues.';

    public function handle(RabbitMQConnectionFactory $connectionFactory): int
    {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();

        $exchange = config('rabbitmq.exchange');

        /*
         * Direct exchange keeps routing explicit:
         * transactional notifications go to high-priority queue,
         * marketing notifications go to default queue.
         */
        $channel->exchange_declare(
            exchange: $exchange,
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false,
        );

        foreach (config('rabbitmq.queues') as $key => $queue) {
            $routingKey = config("rabbitmq.routing_keys.$key");

            $channel->queue_declare(
                queue: $queue,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            $channel->queue_bind(
                queue: $queue,
                exchange: $exchange,
                routing_key: $routingKey,
            );
        }

        $channel->close();
        $connection->close();

        $this->info('RabbitMQ exchange and queues declared successfully.');

        return self::SUCCESS;
    }
}
