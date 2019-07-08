<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Events\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\WithJobStateTrait;
use AvtoDev\AmqpRabbitLaravelQueue\StoreStateInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;

class QueueJobWithSavedState extends SimpleQueueJob implements StoreStateInterface
{
    use WithJobStateTrait, InteractsWithQueue;

    /**
     * Magic name of state name.
     */
    public const MAGIC_PROPERTY = 'magic_counter';

    /**
     * Count of tries for calculate in test.
     */
    public const COUNT_OF_TRIES = 4;

    /**
     * Increment value for each iteration after Throw.
     */
    public const ITERATION_INCREMENT = 23;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = self::COUNT_OF_TRIES;

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    public function handle(Dispatcher $events): void
    {
        $state       = $this->getState();
        $magic_value = ($state[static::MAGIC_PROPERTY] ?? 0) + static::ITERATION_INCREMENT;

        $key = static::class . '-handled';

        if (Sharer::has($key)) {
            Sharer::put($key, Sharer::get($key) + 1);
        } else {
            Sharer::put($key, 1);
        }

        Sharer::put(static::class . '-when', (new \DateTime)->getTimestamp());

        $this->setState([static::MAGIC_PROPERTY => $magic_value]);

        Sharer::put(static::MAGIC_PROPERTY, $magic_value);
        throw new \InvalidArgumentException;
    }
}
