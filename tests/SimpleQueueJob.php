<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        event(static::class . '-failed');
    }
}
