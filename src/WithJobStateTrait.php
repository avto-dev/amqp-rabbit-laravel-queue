<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use AvtoDev\AmqpRabbitLaravelQueue\Job as RabbitJob;

trait WithJobStateTrait
{
    /**
     * Store state in job.
     *
     * @param mixed $data Data must allows serialization
     */
    public function setState($data)
    {
        if ($this->job instanceof RabbitJob) {
            $this->job->setMessageContext($data);
        }
    }

    /**
     * Returns stored state of job.
     *
     * @param $default
     *
     * @return mixed|null
     */
    public function getState($default = null)
    {
        return $this->job instanceof RabbitJob
            ? $this->job->getMessageContext($default)
            : null;
    }
}
