<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Mockery as m;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Enqueue\AmqpExt\AmqpContext;
use Enqueue\AmqpExt\AmqpConsumer;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Interop\Queue\SubscriptionConsumer;
use AvtoDev\AmqpRabbitLaravelQueue\Queue;
use Illuminate\Queue\Events\JobProcessed;
use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Queue\Events\JobProcessing;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\SimpleQueueJob;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobThatThrowsException;
use Illuminate\Queue\Connectors\ConnectorInterface as IlluminateQueueConnector;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Worker
 *
 * @group  queue
 * @group  usesExternalServices
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
            return $this->app->make(Connector::class);
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

    /**
     * @medium
     *
     * @return void
     */
    public function testDaemonBasic(): void
    {
        Event::fake();

        $this->worker = m::mock(Worker::class, [
            $this->app->make(QueueManager::class),
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
            static function () {
            },
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
        \usleep(1800);
        $this->assertSame(1, $queue->size());

        $this->worker->daemon($this->queue_connection_name, 'default', new WorkerOptions(0, 32, -1));

        $this->assertSame(0, $queue->size());

        Event::assertDispatched(JobProcessing::class);
        Event::assertDispatched(JobProcessed::class);
        Event::assertDispatched(SimpleQueueJob::class . '-handled');

        Event::assertNotDispatched(SimpleQueueJob::class . '-failed');
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testDaemonJobFails(): void
    {
        Event::fake();

        $this->worker = m::mock(Worker::class, [
            $this->app->make(QueueManager::class),
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
            static function () {
            },
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
        \usleep(8500);
        $this->assertSame(1, $queue->size());

        $this->worker->daemon($this->queue_connection_name, 'default', new WorkerOptions(0, 32, -1, 3, 2));
        \usleep(8500);
        $this->assertSame(0, $queue->size()); // There is no 'jobs.failer', so, failed job should be 'deleted

        Event::assertDispatched(JobProcessing::class);

        Event::assertNotDispatched(QueueJobThatThrowsException::class . '-handled');
        Event::assertNotDispatched(JobProcessed::class);
    }

    /**
     * @return void
     */
    public function testConsumerTagPrefix(): void
    {
        $manager = m::mock(QueueManager::class)
            ->makePartial()
            ->shouldReceive('connection')
            ->andReturn($queue = m::mock(Queue::class))
            ->getMock();
        $queue
            ->shouldReceive('getRabbitConnection')
            ->andReturn($context = m::mock(AmqpContext::class))
            ->getMock()
            ->shouldReceive('getRabbitQueue')
            ->andReturn(m::mock(AmqpQueue::class))
            ->getMock()
            ->shouldReceive('shouldResume')
            ->andReturnFalse()
            ->getMock();
        $context
            ->shouldReceive('createConsumer')
            ->andReturn($consumer = m::mock(AmqpConsumer::class))
            ->getMock()
            ->shouldReceive('createSubscriptionConsumer')
            ->andReturn($subscriber = m::mock(SubscriptionConsumer::class))
            ->getMock()
            ->shouldReceive('close')
            ->getMock();

        $prefix = Str::random(5);
        $consumer
            ->expects('setConsumerTag')
            ->withArgs(function ($arg) use ($prefix) {
                $this->assertMatchesRegularExpression("/^$prefix.*/", $arg);

                return true;
            })
            ->getMock();
        $subscriber
            ->shouldReceive('subscribe')
            ->getMock()
            ->shouldReceive('consume')
            ->getMock();

        $worker = new Worker(
            $manager,
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
            static function () {
            },
            $prefix
        );

        $worker->daemon(Str::random(), 'default', new WorkerOptions());
    }
}
