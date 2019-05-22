<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use Illuminate\Contracts\Events\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;

class QueueJobThatThrowsException extends SimpleQueueJob
{
    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(Dispatcher $events): void
    {
        $key = static::class . '-throws';

        if (Sharer::has($key)) {
            Sharer::put($key, Sharer::get($key) + 1);
        } else {
            Sharer::put($key, 1);
        }

        throw new \Exception('Test job failed');
    }
}
