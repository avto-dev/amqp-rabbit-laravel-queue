<?php

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;

return [
    'connections' => [
        'rabbit-default' => [
            'host'  => env('RABBIT_HOST', 'rabbitmq'),
            'port'  => (int) env('RABBIT_PORT', 5672),
            'vhost' => env('RABBIT_VHOST', '/'),
            'user'  => env('RABBIT_LOGIN', 'guest'),
            'pass'  => env('RABBIT_PASSWORD', 'guest'),
        ],
    ],

    'default_connection' => 'rabbit-default',

    'queues' => [
        'jobs' => [
            'name'         => env('JOBS_QUEUE_NAME', 'jobs'),
            'flags'        => AmqpQueue::FLAG_DURABLE,
            'arguments'    => [
                'x-max-priority' => 255,
            ],
            'consumer_tag' => null,
        ],

        'failed' => [
            'name'         => env('FAILED_JOBS_QUEUE_NAME', 'failed-jobs'),
            'flags'        => AmqpQueue::FLAG_DURABLE,
            'arguments'    => [
                'x-message-ttl' => 604800000,
                'x-queue-mode'  => 'lazy',
            ],
            'consumer_tag' => null,
        ],
    ],

    'exchanges' => [

        'delayed-jobs' => [
            'name'      => env('DELAYED_JOBS_EXCHANGE_NAME', 'jobs.delayed'),
            'type'      => 'x-delayed-message',
            'flags'     => AmqpTopic::FLAG_DURABLE,
            'arguments' => [
                'x-delayed-type' => AmqpTopic::TYPE_DIRECT,
            ],
        ],

    ],

    'setup' => [
        'rabbit-default' => [
            'queues'    => [
                'jobs',
                'failed',
            ],
            'exchanges' => [
                'delayed-jobs',
            ],
        ],
    ],
];
