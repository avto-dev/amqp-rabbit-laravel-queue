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
class RabbitQueueFailedJobProvider implements FailedJobProviderInterface, \Countable
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
     * Log a failed job into storage.
     *
     * @param string    $connection_name
     * @param string    $queue_name
     * @param string    $message_body
     * @param Exception $exception
     *
     * @return int|null
     */
    public function log($connection_name, $queue_name, $message_body, $exception)
    {
        $timestamp = (new DateTime)->getTimestamp();

        $message = $this->connection->createMessage($message_body, [
            self::PROPERTY_FAILED_AT       => $timestamp,
            self::PROPERTY_CONNECTION_NAME => $connection_name,
            self::PROPERTY_QUEUE_NAME      => $queue_name,
            self::PROPERTY_EXCEPTION       => Str::limit((string) $exception, 10240),
        ], [
            'app_id'       => 'jobs-failer',
            'timestamp'    => $timestamp,
            'content_type' => 'application/json',
        ]);

        $id = self::generateMessageId($message_body, \microtime(true));

        $message->setMessageId((string) $id);

        $this->connection->createProducer()->send($this->queue, $message);

        return $id;
    }

    /**
     * Generate failed job message ID.
     *
     * @param mixed ...$arguments
     *
     * @return int
     */
    public static function generateMessageId(...$arguments): int
    {
        return (int) Str::limit((string) \crc32(\serialize($arguments)), 8, '');
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
            if (\property_exists($job, 'id') && \is_numeric($id) && ((int) $job->id) === ((int) $id)) {
                return $job;
            }
        }
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @throws Throwable
     *
     * @return array<object>
     */
    public function all(): array
    {
        $result = [];

        $this->filterMessagesInQueue($this->queue, function (Message $message) use (&$result): void {
            $job = new stdClass;

            $job->id         = (int) $message->getMessageId();
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

        if (\is_numeric($id)) {
            $id = (int) $id;
            $this->filterMessagesInQueue($this->queue, function (Message $message) use (&$id, &$deleted_count): bool {
                $message_id = $message->getMessageId();

                if (! empty($message_id) && ((int) $message_id) === $id) {
                    $deleted_count++;

                    return false;
                }

                return true;
            });
        }

        return $deleted_count > 0;
    }

    /**
     * {@inheritdoc}
     *
     * @param  int|null  $hours
     * @return void
     */
    public function flush($hours = null): void
    {
        $this->connection->purgeQueue($this->queue);
    }

    /**
     * Get count of failed jobs.
     *
     * !!! You should avoid to use this method (broker does not guarantee operations order) !!!
     *
     * @param int|null $sleep Sleep for a some time before broker calling, in micro seconds
     *
     * @return int
     */
    public function count(?int $sleep = 3000): int
    {
        if (\is_int($sleep)) {
            \usleep($sleep); // Required for broker (for calling in a loop)
        }

        return $this->connection->declareQueue($this->queue);
    }

    /**
     * Move messages into temporary queue and back after, using closure for messages filtering.
     *
     * Closure CAN allows `Message $message` as first argument, an if closure returns FALSE - message will be skipped.
     *
     * !!! You should avoid to use this method !!!
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
