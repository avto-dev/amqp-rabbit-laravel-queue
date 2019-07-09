<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use RuntimeException;

/**
 * @property Job $job
 */
trait WithJobStateTrait
{
    /**
     * Store state in job.
     *
     * @throws RuntimeException Must have the property job
     *
     * @return JobStateInterface
     */
    public function getState()
    {
        if (! \property_exists($this, 'job') || ! $this->job instanceof Job) {
            throw new RuntimeException('Must have the property job');
        }

        return $this->job->state();
    }
}
