<?php

use Interop\Amqp\AmqpQueue;

return [
    'connections' => [
        'testing' => [
            'host'     => env('RABBIT_HOST', 'rabbitmq'),
            'port'     => (int) env('RABBIT_PORT', 5672),
            'vhost'    => env('RABBIT_VHOST', '/'),
            'login'    => env('RABBIT_LOGIN', 'guest'),
            'password' => env('RABBIT_PASSWORD', 'guest'),
        ],
    ],

    'default_connection' => 'testing',

    'queues' => [
        'jobs'   => [
            'name'         => 'jobs',
            'flags'        => AmqpQueue::FLAG_DURABLE, // Durable queues remain active when a server restarts
            'arguments'    => [
                'x-max-priority' => 255, // @link <https://www.rabbitmq.com/priority.html>
            ],
            'consumer_tag' => null,
        ],
        'failed' => [
            'name'         => 'failed',
            'flags'        => AmqpQueue::FLAG_DURABLE, // Durable queues remain active when a server restarts
            'arguments'    => [
                'x-message-ttl' => 604800000, // 7 days (60×60×24×7×1000), @link <https://www.rabbitmq.com/ttl.html>
                'x-queue-mode'  => 'lazy', // @link <https://www.rabbitmq.com/lazy-queues.html>
            ],
            'consumer_tag' => null,
        ],
    ],

    'setup' => [
        'testing' => ['jobs', 'failed'],
    ],
];
