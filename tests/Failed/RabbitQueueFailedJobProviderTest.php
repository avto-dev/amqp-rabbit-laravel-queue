<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Failed;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpMessage as Message;
use Illuminate\Contracts\Debug\ExceptionHandler;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider<extended>
 */
class RabbitQueueFailedJobProviderTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var RabbitQueueFailedJobProvider
     */
    protected $provider;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new RabbitQueueFailedJobProvider(
            $this->temp_rabbit_connection,
            $this->temp_rabbit_queue,
            $this->app->make(ExceptionHandler::class)
        );
    }

    /**
     * @small
     *
     * @return void
     */
    public function testLog(): void
    {
        $this->assertSame(0, $this->provider->count());

        $id = $this->provider->log(
            $connection_name = Str::random(),
            $queue_name = Str::random(),
            $message_body = Str::random(),
            $exception = new \Exception(Str::random())
        );

        $this->assertSame(1, $this->provider->count());

        $message = $this->getLastMessage();

        $this->assertSame($connection_name, $message->getProperty('job-connection-name'));
        $this->assertInternalType('numeric', $message->getProperty('job-failed-at'));
        $this->assertSame($queue_name, $message->getProperty('job-queue-name'));
        $this->assertSame((string) $exception, $message->getProperty('job-exception'));
        $this->assertSame($message_body, $message->getBody());

        $this->assertSame('jobs-failer', $message->getHeader('app_id'));
        $this->assertSame('application/json', $message->getHeader('content_type'));
        $this->assertSame($id, (int) $message->getMessageId());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGenerateMessageId(): void
    {
        $this->assertInternalType('numeric', $this->provider::generateMessageId('foo'));
        $this->assertInternalType('numeric', $this->provider::generateMessageId(['foo']));
        $this->assertInternalType('numeric', $this->provider::generateMessageId(['foo', false]));
        $this->assertInternalType('numeric', $this->provider::generateMessageId(['foo', false], 123));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testFind(): void
    {
        $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        $id2 = $this->provider->log(
            $connection_name2 = Str::random(),
            $queue_name2 = Str::random(),
            $message_body2 = Str::random(),
            $exception2 = new \Exception(Str::random())
        );

        $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(7000);

        $this->assertSame(3, $this->provider->count());

        $found = $this->provider->find($id2);

        $this->assertSame($id2, $found->id);
        $this->assertSame($connection_name2, $found->connection);
        $this->assertSame($queue_name2, $found->queue);
        $this->assertSame($message_body2, $found->payload);
        $this->assertSame((string) $exception2, $found->exception);
        $this->assertInstanceOf(Carbon::class, $found->failed_at);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testAll(): void
    {
        $id1 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $id2 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $id3 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        $this->assertSame(3, $this->provider->count());

        $all = $this->provider->all();

        $this->assertSame($id1, $all[0]->id);
        $this->assertSame($id2, $all[1]->id);
        $this->assertSame($id3, $all[2]->id);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testForget(): void
    {
        $id1 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $id2 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $id3 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $this->assertSame(3, $this->provider->count());

        $this->provider->forget($id2);

        $this->assertSame(2, $this->provider->count());

        $all = $this->provider->all();

        $this->assertSame($id1, $all[0]->id);
        $this->assertSame($id3, $all[1]->id);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testFlush(): void
    {
        $id1 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $id2 = $this->provider->log(
            Str::random(),
            Str::random(),
            Str::random(),
            new \Exception(Str::random())
        );

        \usleep(2000);

        $this->assertSame(2, $this->provider->count());

        $this->provider->flush();

        $this->assertSame(0, $this->provider->count());
    }

    /**
     * @param int $timeout
     *
     * @return Message
     */
    protected function getLastMessage(int $timeout = 1500): Message
    {
        return $this->temp_rabbit_connection->createConsumer($this->temp_rabbit_queue)->receive($timeout);
    }
}
