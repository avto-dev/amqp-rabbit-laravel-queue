<?php

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Serializable;

interface JobStateInterface extends Serializable
{
    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all();

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key);

    /**
     * Get an item from the collection by key.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Put an item in the collection by key.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function put($key, $value);
}
