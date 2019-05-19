<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Mockery as m;
use Illuminate\Support\Str;
use AvtoDev\AmqpRabbitLaravelQueue\Job;
use Interop\Amqp\AmqpMessage as Message;
use Interop\Amqp\Impl\AmqpQueue as Queue;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Illuminate\Contracts\Queue\Job as JobContract;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Job<extended>
 *
 * @group  queue
 */
class JobTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var Job
     */
    protected $job;

    /**
     * @var Message
     */
    protected $message;

    /**
     * @var Consumer
     */
    protected $consumer;

    /**
     * @small
     *
     * @return void
     */
    public function testDelete(): void
    {
        $this->mockProperty(
            $this->job,
            'consumer',
            m::mock($this->consumer)
                ->expects('acknowledge')
                ->with($this->message)
                ->once()
                ->getMock()
        );

        $this->assertFalse($this->job->isDeleted());

        $this->job->delete();

        $this->assertTrue($this->job->isDeleted());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testReleaseWithoutDelay(): void
    {
        $producer = m::mock($this->temp_rabbit_connection->createProducer())
            ->expects('setDeliveryDelay')
            ->never()// !!!
            ->andReturnSelf()
            ->getMock()
            ->expects('send')
            ->once()
            ->withArgs(function (Queue $queue, Message $message): bool {
                $this->assertSame(2, $message->getProperty('job-attempts'));

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->mockProperty(
            $this->job,
            'consumer',
            m::mock($this->consumer)
                ->expects('acknowledge')
                ->with($this->message)
                ->once()
                ->getMock()
        );

        $this->mockProperty(
            $this->job,
            'connection',
            m::mock($this->temp_rabbit_connection)
                ->expects('createProducer')
                ->once()
                ->andReturn($producer)
                ->getMock()
        );

        $this->assertFalse($this->job->isReleased());

        $this->job->release();

        $this->assertTrue($this->job->isReleased());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testReleaseWithDelay(): void
    {
        $delay = \random_int(1, 10);

        $producer = m::mock($this->temp_rabbit_connection->createProducer())
            ->expects('setDeliveryDelay')
            ->once()// !!!
            ->with($delay * 1000)
            ->andReturnSelf()
            ->getMock()
            ->expects('send')
            ->once()
            ->withArgs(function (Queue $queue, Message $message): bool {
                $this->assertSame(2, $message->getProperty('job-attempts'));

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->mockProperty(
            $this->job,
            'consumer',
            m::mock($this->consumer)
                ->expects('acknowledge')
                ->with($this->message)
                ->once()
                ->getMock()
        );

        $this->mockProperty(
            $this->job,
            'connection',
            m::mock($this->temp_rabbit_connection)
                ->expects('createProducer')
                ->once()
                ->andReturn($producer)
                ->getMock()
        );

        $this->assertFalse($this->job->isReleased());

        $this->job->release($delay);

        $this->assertTrue($this->job->isReleased());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetters(): void
    {
        // Get Queue

        $this->assertSame($this->temp_rabbit_queue_name, $this->job->getQueue());

        // Get Raw Body

        $this->assertSame($this->message->getBody(), $this->job->getRawBody());

        // Get Attempts

        $this->assertSame(1, $this->job->attempts());

        $this->message->setProperty('job-attempts', $attempts = \random_int(2, 100));

        $this->mockProperty(
            $this->job,
            'connection',
            $this->message
        );

        $this->assertSame($attempts, $this->job->attempts());

        // Get Job Id

        $this->message->setMessageId($message_id = Str::random());

        $this->assertSame($message_id, $this->job->getJobId());

        // Get Message

        $this->assertSame($this->message, $this->job->getMessage());
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Send message to the queue
        $this
            ->temp_rabbit_connection
            ->createProducer()
            ->send(
                $this->temp_rabbit_queue,
                $this->temp_rabbit_connection->createMessage('{"foo":"bar"}')
            );

        // Create consumer
        $this->consumer = $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue);

        // And get the message back
        $this->message = $this->consumer->receive(200);

        $this->job = new Job(
            $this->app,
            $this->temp_rabbit_connection,
            $this->consumer,
            $this->message,
            Str::random()
        );

        // testInstanceOf
        $this->assertInstanceOf(JobContract::class, $this->job);
    }
}
