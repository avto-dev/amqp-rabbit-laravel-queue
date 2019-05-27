<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use InvalidArgumentException;
use AvtoDev\AmqpRabbitLaravelQueue\Queue;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use Illuminate\Queue\Connectors\ConnectorInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Connector<extended>
 *
 * @group  queue
 */
class ConnectorTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = $this->app->make(Connector::class);

        $this->assertInstanceOf(ConnectorInterface::class, $this->connector);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testConnectWithParametersPassing(): void
    {
        $this->assertInstanceOf(Queue::class, $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            'timeout'    => 0,
        ]));

        // Connect Set Default Timeout Value

        /* @var Queue $queue */
        $this->assertInstanceOf(Queue::class, $queue = $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            //'timeout' => 0,
        ]));

        $this->assertSame(0, $queue->getTimeout());

        // Connect With Passing Timeout Value

        /* @var Queue $queue */
        $this->assertInstanceOf(Queue::class, $queue = $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            'timeout'    => $timeout = \random_int(1, 99),
        ]));

        $this->assertSame($timeout, $queue->getTimeout());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testCommentWithResumeParameter(): void
    {
        /* @var Queue $queue */
        $this->assertInstanceOf(Queue::class, $queue = $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            'timeout'    => 100,
            'resume'     => $resume = true,
        ]));

        $this->assertSame($resume, $queue->shouldResume());

        /* @var Queue $queue */
        $this->assertInstanceOf(Queue::class, $queue = $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            'queue_id'   => $this->temp_rabbit_queue_id,
            'timeout'    => 100,
            'resume'     => $resume = false,
        ]));

        $this->assertSame($resume, $queue->shouldResume());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testExceptionThrownWithoutConnectionPassing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->assertInstanceOf(Queue::class, $this->connector->connect([
            //'connection'  => $this->connection_name,
            'queue_id' => $this->temp_rabbit_queue_id,
            'timeout'  => 0,
        ]));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testExceptionThrownWithoutQueueIdPassing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->assertInstanceOf(Queue::class, $this->connector->connect([
            'connection' => $this->temp_rabbit_connection_name,
            //'queue_id'    => $this->queue_id,
            'timeout'    => 0,
        ]));
    }
}
