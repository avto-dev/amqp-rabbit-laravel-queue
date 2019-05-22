<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Str;

trait InteractsWithJobsTrait
{
    /**
     * Normalize priority value (to 0..255).
     *
     * @param int $value
     *
     * @return int
     */
    protected function normalizePriorityValue(int $value): int
    {
        // negative values to zero
        $value = \max(0, $value);

        // limit max value to 255
        return $value >= 255
            ? 255
            : $value;
    }

    /**
     * Convert passed delay value to milliseconds.
     *
     * If passed value has integer type - delay will be interpreted as a SECONDS (10 = 10 seconds). If float -
     * 0.5 = 500 milliseconds, 0.1 = 100 milliseconds. Date*Interval interpreted as usual.
     *
     * @param DateTimeInterface|DateInterval|int|float $delay
     *
     * @return int
     */
    protected function delayToMilliseconds($delay): int
    {
        return (int) (\is_float($delay)
            ? $delay * 1000
            : $this->secondsUntil($delay) * 1000);
    }

    /**
     * Get the number of seconds until the given DateTime.
     *
     * @param DateTimeInterface|DateInterval|int $delay
     *
     * @return int
     */
    abstract protected function secondsUntil($delay);

    /**
     * Generate message ID.
     *
     * @param string $prefix
     * @param mixed  ...$arguments
     *
     * @return string
     */
    protected function generateMessageId(string $prefix = '', ...$arguments): string
    {
        return $prefix . Str::substr(\sha1(\serialize($arguments)), 0, 8);
    }
}
