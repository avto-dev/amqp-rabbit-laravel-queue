<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use DateTime;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Enqueue\AmqpExt\AmqpProducer as Producer;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Enqueue\AmqpTools\RabbitMqDelayPluginDelayStrategy;

class Queue extends \Illuminate\Queue\Queue implements QueueContract
{
    /**
     * @var AmqpQueue
     */
    protected $queue;

    /**
     * @var int
     */
    protected $time_to_run;

    /**
     * @var Context
     */
    protected $connection;

    /**
     * Queue constructor.
     *
     * @param Container $container
     * @param Context   $connection
     * @param AmqpQueue $queue
     * @param int       $time_to_run Timeout in milliseconds
     */
    public function __construct(Container $container, Context $connection, AmqpQueue $queue, int $time_to_run = 0)
    {
        $this->container   = $container;
        $this->connection  = $connection;
        $this->queue       = $queue;
        $this->time_to_run = \max(0, $time_to_run);
    }

    /**
     * {@inheritdoc}
     *
     * @param int|null $sleep Sleep for a some time before broker calling, in micro seconds
     */
    public function size($queue = null, ?int $sleep = 2000): int
    {
        if (\is_int($sleep)) {
            \usleep($sleep); // Required for broker (for calling in a loop)
        }

        return $this->connection->declareQueue($this->queue);
    }

    /**
     * {@inheritdoc}
     *
     * @param object|string $job
     * @param int|null      $priority Message priority
     */
    public function push($job, $data = '', $queue = null, ?int $priority = null): void
    {
        $options = [];

        if (\is_object($job) && $job instanceof PrioritizedJobInterface) {
            $options['priority'] = $job->priority();
        } elseif (\is_int($priority)) {
            $options['priority'] = $priority;
        }

        $this->pushRaw($this->createPayload($job, $queue, $data), $queue, $options);
    }

    /**
     * {@inheritdoc}
     *
     * `$options['delay']` can be passed as integer or float value. In this case, values will be interpreted as a
     * SECONDS (1 = 1 sec, 0.5 = 500 milliseconds, 10 = 10 seconds, 0.1 = 100 milliseconds)
     */
    public function pushRaw($payload, $queue = null, array $options = []): void
    {
        $message = $this->connection->createMessage($payload, [], [
            'timestamp'    => (new DateTime)->getTimestamp(),
            'content_type' => 'application/json',
        ]);

        $producer = $this->connection->createProducer();

        if (isset($options['priority']) && \is_int($priority = $options['priority'])) {
            $message->setPriority($this->normalizePriorityValue($priority));
        }

        if (isset($options['delay']) && \is_numeric($delay = $options['delay'])) {
            $producer->setDelayStrategy(new RabbitMqDelayPluginDelayStrategy);
            $producer->setDeliveryDelay((int) (\is_float($delay)
                ? $delay * 1000
                : $this->secondsUntil($delay) * 1000));
        }

        $message->setDeliveryMode(Message::DELIVERY_MODE_PERSISTENT);
        $message->setMessageId(self::generateMessageId($payload, \microtime(true), Str::random()));

        $this->sendMessage($producer, $this->queue, $message);
    }

    /**
     * Normalize priority value (to 0..255).
     *
     * @param int $value
     *
     * @return int
     */
    protected function normalizePriorityValue(int $value): int
    {
        // negative values to zero
        $value = \max(0, $value);

        // limit max value to 255
        return $value >= 255
            ? 255
            : $value;
    }

    /**
     * Generate message ID.
     *
     * @param mixed ...$arguments
     *
     * @return string
     */
    public static function generateMessageId(...$arguments): string
    {
        return 'job-' . Str::substr(\sha1(\serialize($arguments)), 0, 8);
    }

    /**
     * Send message using AMQP producer.
     *
     * @param Producer  $producer
     * @param AmqpQueue $queue
     * @param Message   $message
     *
     * @return void
     */
    protected function sendMessage(Producer $producer, AmqpQueue $queue, Message $message): void
    {
        $producer->send($queue, $message);
    }

    /**
     * {@inheritdoc}
     *
     * @param float|int $delay    Delay in seconds
     * @param int|null  $priority Message priority
     */
    public function later($delay, $job, $data = '', $queue = null, ?int $priority = null): void
    {
        $options = [
            'delay' => $delay,
        ];

        if (\is_object($job) && $job instanceof PrioritizedJobInterface) {
            $options['priority'] = $job->priority();
        } elseif (\is_int($priority)) {
            $options['priority'] = $priority;
        }

        $this->pushRaw($this->createPayload($job, $queue, $data), $queue, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queue = null): ?Job
    {
        $consumer = $this->connection->createConsumer($this->queue);

        if ($message = $consumer->receive($this->getTimeToRun())) {
            if ($message instanceof Message) {
                return $this->convertMessageToJob($message, $consumer);
            }
        }

        return null;
    }

    /**
     * Get time to run (timeout in milliseconds).
     *
     * @return int
     */
    public function getTimeToRun(): int
    {
        return $this->time_to_run;
    }

    /**
     * Convert message into job instance.
     *
     * @param Message  $message
     * @param Consumer $consumer
     *
     * @return Job
     */
    public function convertMessageToJob(Message $message, Consumer $consumer): Job
    {
        return new Job(
            $this->container,
            $this->connection,
            $consumer,
            $message,
            $this->connectionName
        );
    }

    /**
     * Get RabbitMQ connection.
     *
     * @return Context
     */
    public function getRabbitConnection(): Context
    {
        return $this->connection;
    }

    /**
     * Get RabbitMQ queue.
     *
     * @return AmqpQueue
     */
    public function getRabbitQueue(): AmqpQueue
    {
        return $this->queue;
    }
}
