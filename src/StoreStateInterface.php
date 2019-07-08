<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

/**
 * Interface StoreStateInterface.
 *
 * Store state in job's message.
 */
interface StoreStateInterface
{
    /**
     * Store state in job.
     *
     * @param mixed $data
     */
    public function setState($data);

    /**
     * Returns stored state of job.
     *
     * @return mixed|null
     */
    public function getState();
}
