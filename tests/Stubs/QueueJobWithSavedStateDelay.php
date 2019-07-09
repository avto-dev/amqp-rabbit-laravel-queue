<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Events\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\WithJobStateTrait;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;

class QueueJobWithSavedStateDelay extends SimpleQueueJob
{
    use WithJobStateTrait, InteractsWithQueue;

    /**
     * {@inheritdoc}
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

        $magic_value = $this->getState()->get('state-counter', 0) + 1;

        $this->getState()->put('state-counter', $magic_value);

        Sharer::put(static::class . '-state-counter', $magic_value);

        $events->dispatch(static::class . '-handled');
    }
}
