<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use Illuminate\Console\Command;

final class SetupRabbitMQCommand extends Command
{
    private const EXCHANGE_TYPE_DIRECT = 'direct';

    protected $signature = 'rabbitmq:setup';

    protected $description = 'Declare RabbitMQ exchange and notification queues.';

    /**
     * @throws \Exception
     */
    public function handle(RabbitMQConnectionFactory $connectionFactory): int
    {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();

        $exchange = (string)config('rabbitmq.exchange');

        /*
         * A durable direct exchange is used because routing rules are explicit:
         * transactional notifications are routed to the high-priority queue,
         * while marketing notifications are routed to the default queue.
         */
        $channel->exchange_declare(
            exchange: $exchange,
            type: self::EXCHANGE_TYPE_DIRECT,
            durable: true,
            auto_delete: false,
        );

        foreach ((array)config('rabbitmq.queues') as $key => $queue) {
            $routingKey = (string)config("rabbitmq.routing_keys.$key");

            /*
             * Queues are durable so RabbitMQ can keep messages after broker restart
             * when messages are also published as persistent.
             */
            $channel->queue_declare(
                queue: (string)$queue,
                durable: true,
                auto_delete: false,
            );

            $channel->queue_bind(
                queue: (string)$queue,
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
