<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Events\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\WithJobStateTrait;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;

class QueueJobWithSavedState extends SimpleQueueJob
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

        $magic_value = $this->getState()->get('state-counter', 0) + 1;

        $this->getState()->put('state-counter', $magic_value);

        Sharer::put(static::class . '-state-counter', $magic_value);

        throw new \InvalidArgumentException;
    }
}
