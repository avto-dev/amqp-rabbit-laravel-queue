<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Mockery as m;
use Illuminate\Support\Str;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use AvtoDev\AmqpRabbitLaravelQueue\Queue;
use Illuminate\Queue\Events\JobProcessed;
use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Queue\Events\JobProcessing;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\SimpleQueueJob;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobThatThrowsException;
use Illuminate\Queue\Connectors\ConnectorInterface as IlluminateQueueConnector;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Worker<extended>
 *
 * @group  queue
 */
class WorkerTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var Worker
     */
    protected $worker;

    /**
     * @var QueueManager
     */
    protected $queue_manager;

    /**
     * @var string
     */
    protected $queue_connection_name;

    /**
     * @var string
     */
    protected $queue_connector_name;

    /**
     * @medium
     *
     * @return void
     */
    public function testDaemonBasic(): void
    {
        $this->expectsEvents([
            JobProcessing::class, JobProcessed::class, SimpleQueueJob::class . '-handled',
        ]);

        $this->doesntExpectEvents([
            SimpleQueueJob::class . '-failed',
        ]);

        $this->worker = m::mock(Worker::class, [
            $this->app->make(QueueManager::class),
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('stop')
            ->once()
            ->andReturnNull()
            ->getMock()
            ->expects('closeRabbitConnection')
            ->once()
            ->andReturnNull()
            ->getMock();

        /** @var Queue $queue */
        $queue = $this->worker->getManager()->connection($this->queue_connection_name);

        $this->assertSame(0, $queue->size());
        $queue->push(new SimpleQueueJob);
        \usleep(1500);
        $this->assertSame(1, $queue->size());

        $this->worker->daemon($this->queue_connection_name, 'default', new WorkerOptions);

        $this->assertSame(0, $queue->size());
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testDaemonJobFails(): void
    {
        $this->expectsEvents([
            JobProcessing::class,
        ]);

        $this->doesntExpectEvents([
            QueueJobThatThrowsException::class . '-handled', JobProcessed::class,
        ]);

        $this->worker = m::mock(Worker::class, [
            $this->app->make(QueueManager::class),
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('stop')
            ->once()
            ->andReturnNull()
            ->getMock()
            ->expects('closeRabbitConnection')
            ->once()
            ->andReturnNull()
            ->getMock();

        /** @var Queue $queue */
        $queue = $this->worker->getManager()->connection($this->queue_connection_name);

        $this->assertSame(0, $queue->size());
        $queue->push(new QueueJobThatThrowsException);
        \usleep(1500);
        $this->assertSame(1, $queue->size());

        $this->worker->daemon($this->queue_connection_name, 'default', new WorkerOptions(0, 32, 60, 3, 2));
        \usleep(1500);
        $this->assertSame(0, $queue->size()); // There is no 'jobs.failer', so, failed job should be 'deleted
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->worker                = $this->app->make(Worker::class);
        $this->queue_manager         = $this->app->make(QueueManager::class);
        $this->queue_connection_name = 'temp-queue-connection-' . Str::random();
        $this->queue_connector_name  = 'temp-rabbit-connector-' . Str::random();

        $this->config()->set("queue.connections.{$this->queue_connection_name}", [
            'driver'     => $this->queue_connector_name,
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            'timeout'    => 500, // The timeout is in milliseconds
        ]);

        $this->queue_manager->addConnector($this->queue_connector_name, function (): IlluminateQueueConnector {
            return new Connector(
                $this->app,
                $this->app->make(ConnectionsFactoryInterface::class),
                $this->app->make(QueuesFactoryInterface::class)
            );
        });
        $this->config()->offsetUnset('queue.failed');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->config()->offsetUnset("queue.connections.{$this->queue_connection_name}");

        parent::tearDown();
    }
}
