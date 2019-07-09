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
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 4;

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    public function handle(Dispatcher $events): void
    {
        $key = static::class . '-handled';

        if (Sharer::has($key)) {
            Sharer::put($key, Sharer::get($key) + 1);
        } else {
            Sharer::put($key, 1);
        }

        Sharer::put(static::class . '-when', (new \DateTime)->getTimestamp());

        $magic_value = (int) $this->getState('triggered', 0) + 23;

        $this->setState('triggered', $magic_value);

        Sharer::put(static::class . '-triggered', $magic_value);
        throw new \InvalidArgumentException;
    }
}
