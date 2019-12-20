<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitLaravelQueue\WithJobStateTrait;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;

class PrioritizedQueueJobWithState extends PrioritizedQueueJob
{
    use WithJobStateTrait, InteractsWithQueue;

    /**
     * {@inheritdoc}
     */
    public function handle(Dispatcher $events): void
    {
        Sharer::put(static::class . '-handled', 1);

        $this->getState()->put('state-counter', 'state-counter-value');

        Sharer::put(static::class . '-state-counter', 'state-counter-value');

        $events->dispatch(static::class . '-handled');
    }
}
