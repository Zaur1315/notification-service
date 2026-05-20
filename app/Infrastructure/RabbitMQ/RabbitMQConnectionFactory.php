<?php

declare(strict_types=1);

namespace App\Infrastructure\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Creates RabbitMQ AMQP connections from application configuration.
 *
 * The factory keeps connection details in one place so publishers, consumers
 * and setup commands do not need to know how RabbitMQ credentials are stored.
 */
final class RabbitMQConnectionFactory
{
    /**
     * @throws \Exception
     */
    public function create(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: (string)config('rabbitmq.host'),
            port: (int)config('rabbitmq.port'),
            user: (string)config('rabbitmq.user'),
            password: (string)config('rabbitmq.password'),
            vhost: (string)config('rabbitmq.vhost'),
        );
    }
}
