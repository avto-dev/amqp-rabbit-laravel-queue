<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs;

use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
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
     * @return int
     */
    public function getTries(): int
    {
        return $this->tries;
    }

    /**
     * Handle the job.
     *
     * @param Dispatcher $events
     *
     * @return void
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
        $key = static::class . '-failed';

        if (Sharer::has($key)) {
            Sharer::put($key, Sharer::get($key) + 1);
        } else {
            Sharer::put($key, 1);
        }

        event(static::class . '-failed');
    }
}
