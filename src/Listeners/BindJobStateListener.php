<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Listeners;

use AvtoDev\AmqpRabbitLaravelQueue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Container\Container;
use AvtoDev\AmqpRabbitLaravelQueue\JobStateInterface;

class BindJobStateListener
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Create a new BindJobStateListener instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Handle event.
     *
     * @param JobProcessing $event
     */
    public function handle(JobProcessing $event): void
    {
        if ($event->job instanceof Job) {
            $this->container->instance(JobStateInterface::class, $event->job->state());
        }
    }
}
