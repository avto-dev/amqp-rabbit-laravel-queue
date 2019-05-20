<?php

use AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider;

return [
    'default' => 'rabbitmq',

    'connections' => [
        'rabbitmq' => [
            'driver'      => ServiceProvider::DRIVER_NAME,
            'connection'  => 'testing',
            'queue_id'    => 'jobs',
            'time_to_run' => 0, // The timeout is in milliseconds
        ],
    ],

    'failed' => [
        'connection' => 'testing',
        'queue_id'   => 'failed',
    ],
];
