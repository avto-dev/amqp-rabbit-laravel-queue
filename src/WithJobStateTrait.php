<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use AvtoDev\AmqpRabbitLaravelQueue\Job as RabbitJob;

/**
 * @property RabbitJob $job
 */
trait WithJobStateTrait
{
    /**
     * {@inheritdoc}
     */
    public function setState($data)
    {
        if ($this->job instanceof RabbitJob) {
            $this->job->setMessageContext($data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->job instanceof RabbitJob
            ? $this->job->getMessageContext()
            : null;
    }
}
