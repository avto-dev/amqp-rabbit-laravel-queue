<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;

class SimpleQueueJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * Handle the job.
     *
     * @param Dispatcher $events
     *
     * @return void
     */
    public function handle(Dispatcher $events): void
    {
        Sharer::put(static::class . '-handled', true);

        $events->dispatch(static::class . '-handled');
    }

    /**
     * Handle a job failure.
     *
     * @param \Exception|null $exception
     *
     * @return void
     */
    public function failed($exception = null): void
    {
        Sharer::put(static::class . '-failed', true);

        event(static::class . '-failed');
    }
}
