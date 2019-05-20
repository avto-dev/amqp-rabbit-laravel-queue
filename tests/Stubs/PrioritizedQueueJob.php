<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use AvtoDev\AmqpRabbitLaravelQueue\HasPriorityTrait;
use AvtoDev\AmqpRabbitLaravelQueue\PrioritizedJobInterface;

class PrioritizedQueueJob extends SimpleQueueJob implements PrioritizedJobInterface
{
    use HasPriorityTrait;

    /**
     * @var int
     */
    protected $priority;

    /**
     * PrioritizedQueueJob constructor.
     *
     * @param int $priority
     */
    public function __construct(int $priority = 10)
    {
        $this->priority = $priority;
    }
}
