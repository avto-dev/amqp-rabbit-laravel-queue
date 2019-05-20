<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Failed;

use DateTime;
use stdClass;
use Exception;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue as Queue;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * @see \AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider::overrideFailedJobService()
 * @see \Illuminate\Queue\Failed\DatabaseFailedJobProvider
 */
class RabbitQueueFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * Property name for "failed-at".
     */
    protected const PROPERTY_FAILED_AT = 'job-failed-at';

    /**
     * Property name for "connection-name".
     */
    protected const PROPERTY_CONNECTION_NAME = 'job-connection-name';

    /**
     * Property name for "queue-name".
     */
    protected const PROPERTY_QUEUE_NAME = 'job-queue-name';

    /**
     * Property name for "exception".
     */
    protected const PROPERTY_EXCEPTION = 'job-exception';

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var Context
     */
    protected $connection;

    /**
     * @var ExceptionHandler
     */
    protected $exception_handler;

    /**
     * RabbitQueueFailedJobProvider constructor.
     *
     * @param Context          $connection
     * @param Queue            $queue
     * @param ExceptionHandler $exception_handler
     */
    public function __construct(Context $connection, Queue $queue, ExceptionHandler $exception_handler)
    {
        $this->connection        = $connection;
        $this->queue             = $queue;
        $this->exception_handler = $exception_handler;
    }

    /**
     * {@inheritdoc}
     *
     * @return string Pushed message ID
     */
    public function log($connection_name, $queue_name, $message_body, $exception): string
    {
        $timestamp = (new DateTime)->getTimestamp();

        $message = $this->connection->createMessage($message_body, [
            self::PROPERTY_FAILED_AT       => $timestamp,
            self::PROPERTY_CONNECTION_NAME => $connection_name,
            self::PROPERTY_QUEUE_NAME      => $queue_name,
            self::PROPERTY_EXCEPTION       => Str::limit((string) $exception, 10240),
        ], [
            'app_id'       => 'data-sources-failed-jobs',
            'timestamp'    => $timestamp,
            'content_type' => 'application/json',
        ]);

        $message->setMessageId($id = self::generateMessageId($message_body, \microtime(true)));

        $this->connection->createProducer()->send($this->queue, $message);

        return $id;
    }

    /**
     * Generate failed job message ID.
     *
     * @param mixed ...$arguments
     *
     * @return string
     */
    public static function generateMessageId(...$arguments): string
    {
        return 'failed-job-' . Str::substr(\sha1(\serialize($arguments)), 0, 8);
    }

    /**
     * Get a single failed job.
     *
     * @param mixed $id
     *
     * @return object|null|mixed
     */
    public function find($id)
    {
        foreach ($this->all() as $job) {
            if (\property_exists($job, 'id') && $job->id === $id) {
                return $job;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable
     *
     * @return array|object[]
     */
    public function all()
    {
        $result = [];

        $this->filterMessagesInQueue($this->queue, function (Message $message) use (&$result): void {
            $job = new stdClass;

            $job->id         = $message->getMessageId();
            $job->connection = $message->getProperty(self::PROPERTY_CONNECTION_NAME);
            $job->queue      = $message->getProperty(self::PROPERTY_QUEUE_NAME);
            $job->payload    = $message->getBody();
            $job->exception  = $message->getProperty(self::PROPERTY_EXCEPTION);
            $job->failed_at  = Carbon::createFromTimestamp($message->getProperty(self::PROPERTY_FAILED_AT));

            $result[] = $job;
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable
     */
    public function forget($id): bool
    {
        $deleted_count = 0;

        $this->filterMessagesInQueue($this->queue, function (Message $message) use (&$id, &$deleted_count): bool {
            if ($message->getMessageId() === $id) {
                $deleted_count++;

                return false;
            }

            return true;
        });

        return $deleted_count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->connection->purgeQueue($this->queue);
    }

    /**
     * Move messages into temporary queue and back after, using closure for messages filtering.
     *
     * Closure CAN allows `Message $message` as first argument, an if closure returns FALSE - message will be skipped.
     *
     * @param Queue    $queue
     * @param callable $callback
     *
     * @return void
     */
    protected function filterMessagesInQueue(Queue $queue, callable $callback): void
    {
        $temp_queue = $this->connection->createTemporaryQueue();

        $consumer = $this->connection->createConsumer($queue);
        $producer = $this->connection->createProducer();

        // Move all messages to the temporary queue
        while (($message = $consumer->receiveNoWait()) instanceof Message) {
            try {
                $producer->send($temp_queue, $message);
            } catch (Exception $e) {
                $this->exception_handler->report($e);
            }

            $consumer->acknowledge($message); // anyway
        }

        unset($consumer);

        $consumer = $this->connection->createConsumer($temp_queue);

        // And then move back
        while (($message = $consumer->receiveNoWait()) instanceof Message) {
            if ($callback($message) === false) {
                $consumer->acknowledge($message);

                continue;
            }

            try {
                $producer->send($queue, $message);
            } catch (Exception $e) {
                $this->exception_handler->report($e);
            }

            $consumer->acknowledge($message); // anyway too
        }

        $this->connection->deleteQueue($temp_queue); // keep clean
    }
}
