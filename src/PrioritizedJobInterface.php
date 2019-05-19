<?php

namespace AvtoDev\AmqpRabbitLaravelQueue;

interface PrioritizedJobInterface
{
    /**
     * Get job priority (as usual value should be between 0 and 255).
     *
     * @return int
     */
    public function priority(): int;
}
