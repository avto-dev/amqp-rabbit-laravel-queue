<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use DateTime;
use Mockery as m;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpMessage as Message;
use AvtoDev\AmqpRabbitLaravelQueue\Queue;
use Enqueue\AmqpExt\AmqpProducer as Producer;
use Illuminate\Contracts\Queue\Queue as QueueContract;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Queue<extended>
 *
 * @group  queue
 */
class QueueTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var int
     */
    protected $time_to_run = 100;

    /**
     * @small
     *
     * @return void
     */
    public function testSize(): void
    {
        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        $this->assertSame(0, $this->queue->size());

        $this->pushMessage();

        $this->assertSame(1, $this->queue->size());

        $this->pushMessage();

        $this->assertSame(2, $this->queue->size());

        $this->temp_rabbit_connection->deleteQueue($this->temp_rabbit_queue);

        $this->assertSame(0, $this->queue->size());

        $this->pushMessage();

        $this->assertSame(1, $this->queue->size());
    }

    /**
     * Push one message into the queue.
     *
     * @param string $content
     *
     * @return void
     */
    protected function pushMessage(string $content = '{"foo"}'): void
    {
        $this->temp_rabbit_connection->createProducer()->send(
            $this->temp_rabbit_queue,
            $this->temp_rabbit_connection->createMessage($content)
        );
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPush(): void
    {
        // With Basic Job Object

        $this->queue->push(new SimpleQueueJob);

        $message = $this->getQueueMessage();
        $body    = (array) \json_decode($message->getBody(), true);

        $this->assertSame(SimpleQueueJob::class, $body['data']['commandName']);
        $this->assertSame(0, $message->getPriority());
        $this->assertCommonMessageProperties($message);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Prioritized Job Object

        $this->queue->push(new PrioritizedQueueJob($priority = \random_int(1, 255)));

        $message = $this->getQueueMessage();
        $body    = (array) \json_decode($message->getBody(), true);

        $this->assertSame(PrioritizedQueueJob::class, $body['data']['commandName']);
        $this->assertSame($priority, $message->getPriority());
        $this->assertCommonMessageProperties($message);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Priority Value

        $this->queue->push(new SimpleQueueJob, null, null, $priority = \random_int(1, 255));

        $message = $this->getQueueMessage();
        $body    = (array) \json_decode($message->getBody(), true);

        $this->assertSame(SimpleQueueJob::class, $body['data']['commandName']);
        $this->assertSame($priority, $message->getPriority());
        $this->assertCommonMessageProperties($message);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With String Job Object

        $this->queue->push($job_string = 'foobar', [1, 2]);

        $message = $this->getQueueMessage();
        $body    = (array) \json_decode($message->getBody(), true);

        $this->assertSame([1, 2], $body['data']);
        $this->assertSame($job_string, $body['job']);
        $this->assertCommonMessageProperties($message);
        $this->assertSame(0, $message->getPriority());
    }

    /**
     * @return Message|null
     */
    protected function getQueueMessage(): ?Message
    {
        $consumer = $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue);

        $message = $consumer->receive(200);

        if ($message instanceof Message) {
            $consumer->reject($message);

            return $message;
        }

        return null;
    }

    /**
     * Assert message for a common properties and headers.
     *
     * @param Message $message
     * @param int     $allowed_timestamp_delta
     */
    protected function assertCommonMessageProperties(Message $message, int $allowed_timestamp_delta = 500): void
    {
        $this->assertRegExp('~job\-[a-zA-Z0-9]{6,}~', $message->getHeader('message_id'));

        $timestamp         = $message->getHeader('timestamp');
        $current_timestamp = (new DateTime)->getTimestamp();

        $this->assertTrue($timestamp > ($current_timestamp - $allowed_timestamp_delta));
        $this->assertTrue($timestamp < ($current_timestamp + $allowed_timestamp_delta));

        $this->assertSame('application/json', $message->getHeader('content_type'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPushRaw(): void
    {
        // Basic

        $this->queue->pushRaw($payload = 'foobar', null, []);

        $message = $this->getQueueMessage();

        $this->assertSame($payload, $message->getBody());
        $this->assertCommonMessageProperties($message);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Priority

        $this->queue->pushRaw($payload = 'foobar', null, [
            'priority' => $priority = \random_int(1, 255),
        ]);

        $message = $this->getQueueMessage();

        $this->assertSame($payload, $message->getBody());
        $this->assertCommonMessageProperties($message);
        $this->assertSame($priority, $message->getPriority());

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Priority Above Normal

        $this->queue->pushRaw($payload = 'foobar', null, [
            'priority' => $priority = \random_int(256, 1024),
        ]);

        $message = $this->getQueueMessage();

        $this->assertSame($payload, $message->getBody());
        $this->assertCommonMessageProperties($message);
        $this->assertSame(255, $message->getPriority());

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Priority Below Normal

        $this->queue->pushRaw($payload = 'foobar', null, [
            'priority' => $priority = -1,
        ]);

        $message = $this->getQueueMessage();

        $this->assertSame($payload, $message->getBody());
        $this->assertCommonMessageProperties($message);
        $this->assertSame(0, $message->getPriority());

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // Passing Delay As Integer

        $delay = \random_int(1, 100);

        $this->queue = m::mock(Queue::class, [$this->app, $this->temp_rabbit_connection, $this->temp_rabbit_queue])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('sendMessage')
            ->once()
            ->withArgs(function (Producer $producer, AmqpQueue $queue, Message $message) use (&$delay): bool {
                $this->assertSame($delay * 1000, $producer->getDeliveryDelay());

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->queue->pushRaw($payload = 'foobar', null, [
            'delay' => $delay,
        ]);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Delay As Float

        $delay = \M_PI;

        $this->queue = m::mock(Queue::class, [$this->app, $this->temp_rabbit_connection, $this->temp_rabbit_queue])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('sendMessage')
            ->once()
            ->withArgs(function (Producer $producer, AmqpQueue $queue, Message $message) use (&$delay): bool {
                $this->assertSame((int) ($delay * 1000), $producer->getDeliveryDelay());

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->queue->pushRaw($payload = 'foobar', null, [
            'delay' => $delay,
        ]);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testLater(): void
    {
        // Passing Delay As Integer

        $delay    = \random_int(1, 100);
        $priority = \random_int(1, 255);

        $this->queue = m::mock(Queue::class, [$this->app, $this->temp_rabbit_connection, $this->temp_rabbit_queue])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('sendMessage')
            ->once()
            ->withArgs(function (Producer $producer, AmqpQueue $queue, Message $message) use (
                &$delay,
                &$priority
            ): bool {
                $this->assertSame($delay * 1000, $producer->getDeliveryDelay());
                $this->assertSame($priority, $message->getPriority());

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->queue->later($delay, new SimpleQueueJob, null, null, $priority);

        $this->temp_rabbit_connection->purgeQueue($this->temp_rabbit_queue);

        // With Passing Delay As Float

        $delay    = \M_PI;
        $priority = \random_int(1, 255);

        $this->queue = m::mock(Queue::class, [$this->app, $this->temp_rabbit_connection, $this->temp_rabbit_queue])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('sendMessage')
            ->once()
            ->withArgs(function (Producer $producer, AmqpQueue $queue, Message $message) use (
                &$delay,
                &$priority
            ): bool {
                $this->assertSame((int) ($delay * 1000), $producer->getDeliveryDelay());
                $this->assertSame($priority, $message->getPriority());

                return true;
            })
            ->andReturnNull()
            ->getMock();

        $this->queue->later($delay, new PrioritizedQueueJob($priority));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPop(): void
    {
        $this->queue->push(new SimpleQueueJob);

        \usleep(1000);

        $message = $this->queue->pop()->getMessage();
        $body    = (array) \json_decode($message->getBody(), true);

        $this->assertSame(SimpleQueueJob::class, $body['data']['commandName']);
        $this->assertCommonMessageProperties($message);

        // Second call returns null

        $this->assertNull($this->queue->pop());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testConvertMessageToJob(): void
    {
        $this->assertTrue(
            \method_exists($this->queue, $method_name = 'convertMessageToJob'), "Has no method named {$method_name}"
        );
    }

    /**
     * @small
     *
     * @return void
     */
    public function testAdditionalGetters(): void
    {
        $this->assertSame($this->temp_rabbit_connection, $this->queue->getRabbitConnection());
        $this->assertSame($this->temp_rabbit_queue, $this->queue->getRabbitQueue());
        $this->assertSame($this->time_to_run, $this->queue->getTimeToRun());

        $this->assertRegExp('~job\-[a-zA-Z0-9]{6,}~', $this->queue::generateMessageId('foo', 123));
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new Queue(
            $this->app,
            $this->temp_rabbit_connection,
            $this->temp_rabbit_queue,
            $this->time_to_run
        );

        $this->assertInstanceOf(QueueContract::class, $this->queue);
    }
}
