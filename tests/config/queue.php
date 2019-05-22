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
            'timeout'             => 0,
        ],
    ],

    'failed' => [
        'connection' => 'rabbit-default',
        'queue_id'   => 'failed',
    ],
];
