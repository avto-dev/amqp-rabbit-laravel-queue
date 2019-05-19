<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Contracts\Events\Dispatcher;

class QueueJobThatThrowsException extends SimpleQueueJob
{
    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(Dispatcher $events): void
    {
        throw new \Exception('Test job failed');
    }
}
