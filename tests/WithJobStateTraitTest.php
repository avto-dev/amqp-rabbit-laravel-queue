<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use AvtoDev\AmqpRabbitLaravelQueue\Job;
use AvtoDev\AmqpRabbitLaravelQueue\JobStateInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobWithSavedStateDelay;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

use Illuminate\Support\Str;
use Mockery\Mock;
use RuntimeException;
use Illuminate\Queue\InteractsWithQueue;
use AvtoDev\AmqpRabbitLaravelQueue\WithJobStateTrait;

class WithJobStateTraitTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var Job
     */
    protected $job;

    /**
     *{@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Send message to the queue
        $this->temp_rabbit_connection->createProducer()->send(
            $this->temp_rabbit_queue,
            $this->temp_rabbit_connection->createMessage('{"foo":"bar"}')
        );

        $consumer = $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue);
        $message  = $consumer->receive(200);

        $this->job = new Job(
            $this->app,
            $this->temp_rabbit_connection,
            $consumer,
            $message,
            Str::random()
        );
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetStateWithoutProperty(): void
    {
        $this->expectException(RuntimeException::class);
        $object = new class
        {
            use WithJobStateTrait;
        };

        $object->getState();
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetStateWithoutNeededInstanceProperty()
    {
        $this->expectException(RuntimeException::class);
        $object = new class
        {
            use WithJobStateTrait, InteractsWithQueue;
        };

        $object->getState();
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetStateReturnsObject(): void
    {
        /** @var QueueJobWithSavedStateDelay $object */
        $object = $this->app->make(QueueJobWithSavedStateDelay::class);
        $this->mockProperty($object, 'job', $this->job);

        $this->assertInstanceOf(JobStateInterface::class, $object->getState());
    }
}
