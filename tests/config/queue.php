<?php

use AvtoDev\AmqpRabbitLaravelQueue\Connector;

return [
    'default' => 'rabbitmq',

    'connections' => [
        'rabbitmq' => [
            'driver'              => Connector::NAME,
            'connection'          => 'rabbit-default',
            'queue_id'            => 'jobs',
            'delayed_exchange_id' => 'delayed-jobs',
            'timeout'             => (int) env('QUEUE_TIMEOUT', 0),
            'resume'              => (bool) env('QUEUE_RESUME', false),
        ],
    ],

    'failed' => [
        'connection' => 'rabbit-default',
        'queue_id'   => 'failed',
    ],
];
