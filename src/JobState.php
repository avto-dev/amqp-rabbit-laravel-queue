<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Closure;
use InvalidArgumentException;
use Illuminate\Support\Collection;

class JobState extends Collection implements JobStateInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return \serialize($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $this->items = \unserialize($serialized);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException If the type of the value is a Resource or a Closure
     */
    public function put($key, $value)
    {
        if (\is_resource($value) || $value instanceof Closure) {
            throw new InvalidArgumentException;
        }

        parent::put($key, $value);
    }
}
