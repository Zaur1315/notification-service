<?php

declare(strict_types=1);

namespace App\Infrastructure\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMQConnectionFactory
{
    /**
     * @throws \Exception
     */
    public function create(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: config('rabbitmq.host'),
            port: config('rabbitmq.port'),
            user: config('rabbitmq.user'),
            password: config('rabbitmq.password'),
            vhost: config('rabbitmq.vhost'),
        );
    }
}
