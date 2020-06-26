<?php

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Serializable;

interface JobStateInterface extends Serializable
{
    /**
     * Get all items from the state.
     *
     * @return array<string, mixed>
     */
    public function all();

    /**
     * Determine if an item exists in the state by key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key);

    /**
     * Get an item from the state by key.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Put an item in the state by key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function put($key, $value);

    /**
     * Determine if the state is empty or not.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Remove an item from the state by key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function forget($key);
}
