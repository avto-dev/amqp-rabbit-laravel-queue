<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Listeners;

use AvtoDev\AmqpRabbitLaravelQueue\Job;
use AvtoDev\AmqpRabbitLaravelQueue\JobStateInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\BindJobStateListener;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Str;

/**
 * @group  listeners
 *
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Listeners\BindJobStateListener<extended>
 * @group  usesExternalServices
 */
class BindJobStateListenerTest extends AbstractExchangeListenerTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var BindJobStateListener
     */
    protected $listener;

    /**
     * @var string
     */
    protected $listener_class = BindJobStateListener::class;

    /**
     * @small
     *
     * @return void
     */
    public function testHandle(): void
    {
        $this->assertFalse($this->app->bound(JobStateInterface::class));

        // Send message to the queue
        $this
            ->temp_rabbit_connection
            ->createProducer()
            ->send(
                $this->temp_rabbit_queue,
                $this->temp_rabbit_connection->createMessage('{"foo":"bar"}')
            );

        // Create consumer
        $consumer = $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue);

        $this->listener->handle(new JobProcessing(Str::random(), new Job(
            $this->app,
            $this->temp_rabbit_connection,
            $consumer,
            $consumer->receive(200),
            Str::random()
        )));

        unset($consumer);

        $this->assertTrue($this->app->bound(JobStateInterface::class));

        $state = $this->app->make(JobStateInterface::class);

        // Send message to the queue again
        $this
            ->temp_rabbit_connection
            ->createProducer()
            ->send(
                $this->temp_rabbit_queue,
                $this->temp_rabbit_connection->createMessage('{"foo":"bar"}')
            );

        // Create consumer
        $consumer = $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue);

        $this->listener->handle(new JobProcessing(Str::random(), new Job(
            $this->app,
            $this->temp_rabbit_connection,
            $consumer,
            $consumer->receive(200),
            Str::random()
        )));

        // State instance MUST changes
        $this->assertNotSame($state, $this->app->make(JobStateInterface::class));
    }
}
