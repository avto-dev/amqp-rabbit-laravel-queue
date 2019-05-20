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
        Sharer::put(static::class . '-handled', true);

        throw new \Exception('Test job failed');
    }
}
