<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

trait HasPriorityTrait
{
    /**
     * Get job priority.
     *
     * @return int
     */
    public function priority(): int
    {
        // extract property value
        $value = \property_exists($this, $property_name = 'priority')
            ? (int) $this->{$property_name}
            : 0;

        // negative values to zero
        $value = \max(0, $value);

        // limit max value to 255
        return $value >= 255
            ? 255
            : $value;
    }
}
