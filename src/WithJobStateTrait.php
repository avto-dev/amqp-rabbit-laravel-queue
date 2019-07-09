<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use AvtoDev\AmqpRabbitLaravelQueue\Job as RabbitJob;

trait WithJobStateTrait
{
    /**
     * Store state in job.
     *
     * @param string $key
     * @param mixed  $data Data must allows serialization
     */
    public function setState(string $key, $data)
    {
        if ($this->job instanceof RabbitJob) {
            $this->job->state()->put($key, $data);
        }
    }

    /**
     * Returns stored state of job.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getState(string $key, $default = null)
    {
        return $this->job instanceof RabbitJob
            ? $this->job->state()->get($key, $default)
            : null;
    }
}
