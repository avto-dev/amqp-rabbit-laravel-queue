<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

interface StoreStateInterface
{
    /**
     * Store state in job.
     *
     * @param string     $key
     * @param mixed|null $data
     */
    public function setState(string $key, $data);

    /**
     * Returns stored state of job.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getState(string $key, $default = null);
}
