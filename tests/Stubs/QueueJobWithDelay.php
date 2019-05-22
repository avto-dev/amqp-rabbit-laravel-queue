<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

class QueueJobWithDelay extends SimpleQueueJob
{
    /**
     * The number of seconds before the job should be made available.
     *
     * @var \DateTimeInterface|\DateInterval|int|null
     */
    public $delay = 2;
}
