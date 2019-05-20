<?php

use AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider;

return [
    'default' => 'rabbitmq',

    'connections' => [
        'rabbitmq' => [
            'driver'     => ServiceProvider::DRIVER_NAME,
            'connection' => 'testing',
            'queue_id'   => 'jobs',
            'timeout'    => 0, // The timeout is in milliseconds
        ],
    ],

    'failed' => [
        'connection' => 'testing',
        'queue_id'   => 'failed',
    ],
];
