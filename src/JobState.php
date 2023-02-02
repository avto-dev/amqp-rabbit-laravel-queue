<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Closure;
use InvalidArgumentException;
use Illuminate\Support\Collection;

class JobState extends Collection implements JobStateInterface
{
    /**
     * String representation of object.
     *
     * @return string
     */
    public function serialize(): string
    {
        return \serialize($this->items);
    }

    /**
     * Constructs the object.
     *
     * @param string $serialized
     *
     * @return void
     */
    public function unserialize($serialized): void
    {
        $unserialized = \unserialize($serialized, ['allowed_classes' => true]);
        $this->items  = \is_array($unserialized)
            ? $unserialized
            : [];
    }

    /**
     * Put an item in the state by key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws InvalidArgumentException If value type has wrong type
     *
     * @return self<string, mixed>
     */
    public function put($key, $value)
    {
        if (\is_resource($value) || $value instanceof Closure) {
            throw new InvalidArgumentException('Wrong value passed');
        }

        return parent::put($key, $value);
    }
}
