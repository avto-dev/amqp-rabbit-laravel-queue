<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use DateTime;
use RuntimeException;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Enqueue\AmqpExt\AmqpProducer as Producer;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class Queue extends \Illuminate\Queue\Queue implements QueueContract
{
    use InteractsWithJobsTrait;

    /**
     * @var AmqpQueue
     */
    protected $queue;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var Context
     */
    protected $connection;

    /**
     * @var AmqpTopic|null
     */
    protected $delayed_exchange;

    /**
     * @var bool
     */
    protected $resume;

    /**
     * Create a new Queue instance.
     *
     * @param Container      $container
     * @param Context        $connection
     * @param AmqpQueue      $queue
     * @param int            $timeout
     * @param bool           $resume
     * @param AmqpTopic|null $delayed_exchange
     */
    public function __construct(Container $container,
                                Context $connection,
                                AmqpQueue $queue,
                                int $timeout = 0,
                                bool $resume = false,
                                ?AmqpTopic $delayed_exchange = null)
    {
        $this->container        = $container;
        $this->connection       = $connection;
        $this->queue            = $queue;
        $this->timeout          = \max(0, $timeout);
        $this->delayed_exchange = $delayed_exchange;
        $this->resume           = $resume;
    }

    /**
     * {@inheritdoc}
     *
     * !!! You should avoid to use this method (broker does not guarantee operations order) !!!
     *
     * Delayed messages count will be NOT included!
     *
     * @param int|null $sleep Sleep for a some time before broker calling, in micro seconds
     */
    public function size($queue = null, ?int $sleep = 2500): int
    {
        if (\is_int($sleep)) {
            \usleep($sleep); // Required for broker (for calling in a loop)
        }

        return $this->connection->declareQueue($this->queue);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param object|string $job
     * @param mixed         $data
     * @param string        $queue
     * @param int|null      $priority Message priority
     *
     * @return void
     */
    public function push($job, $data = '', $queue = null, ?int $priority = null): void
    {
        $options = [
            'priority' => $priority,
        ];

        if (\is_object($job) && $job instanceof PrioritizedJobInterface) {
            $options['priority'] = $job->priority();
        }

        $this->pushRaw($this->createPayloadCompatible($job, $queue, $data), $queue, $options);
    }

    /**
     * @param string               $payload
     * @param string|null          $queue
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function pushRaw($payload, $queue = null, array $options = []): void
    {
        $message = $this->connection->createMessage($payload, [], [
            'timestamp'    => (new DateTime)->getTimestamp(),
            'content_type' => 'application/json',
        ]);

        $producer  = $this->connection->createProducer();
        $priority  = ($options['priority'] ?? null);
        $delay     = ($options['delay'] ?? null);
        $recipient = $this->queue; // Default way

        $message->setDeliveryMode(Message::DELIVERY_MODE_PERSISTENT);
        $message->setMessageId($this->generateMessageId('job-', $payload, \microtime(true), Str::random()));

        if (\is_int($priority)) {
            $message->setPriority($this->normalizePriorityValue($priority));
        }

        // If delayed exchange are defined and message should be sent with delay - change the recipient
        if ($delay !== null && $this->delayed_exchange instanceof AmqpTopic) {
            $message->setProperty('x-delay', $this->delayToMilliseconds($delay));
            $message->setRoutingKey($this->queue->getQueueName());

            $recipient = $this->delayed_exchange;
        }

        $this->sendMessage($producer, $recipient, $message);
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param string|mixed $job
     * @param string|mixed $queue
     * @param mixed        $data
     *
     * @throws RuntimeException
     * @throws \Illuminate\Queue\InvalidPayloadException
     *
     * @return string
     *
     * @see \Illuminate\Queue\Queue::createPayload()
     */
    public function createPayloadCompatible($job, $queue, $data): string
    {
        static $parameters_number, $method_name = 'createPayload';

        if (! \is_int($parameters_number)) {
            $parameters_number = (new \ReflectionMethod(static::class, $method_name))->getNumberOfParameters();
        }

        if ($parameters_number === 2) {
            // @link: https://github.com/laravel/framework/blob/v5.5.0/src/Illuminate/Queue/Queue.php#L85
            // @link: https://github.com/laravel/framework/blob/v5.6.0/src/Illuminate/Queue/Queue.php#L85
            // @link: https://github.com/laravel/framework/blob/v5.7.0/src/Illuminate/Queue/Queue.php#L78
            return $this->{$method_name}($job, $data);
        }

        if ($parameters_number === 3) {
            // @link: https://github.com/laravel/framework/blob/v5.8.0/src/Illuminate/Queue/Queue.php#L86
            return $this->{$method_name}($job, $queue, $data);
        }

        throw new RuntimeException(
            "Parent method looks like not compatible with current class (uses {$parameters_number} parameters)"
        );
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int|float $delay    Delay in seconds
     * @param string|object                              $job
     * @param mixed                                      $data
     * @param string                                     $queue
     * @param int|null                                   $priority Message priority
     *
     * @return mixed|void
     */
    public function later($delay, $job, $data = '', $queue = null, ?int $priority = null)
    {
        $options = [
            'delay'    => $delay,
            'priority' => $priority,
        ];

        if (\is_object($job) && $job instanceof PrioritizedJobInterface) {
            $options['priority'] = $job->priority();
        }

        $this->pushRaw($this->createPayloadCompatible($job, $queue, $data), $queue, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queue = null): ?Job
    {
        $consumer = $this->connection->createConsumer($this->queue);

        if ($message = $consumer->receive($this->getTimeout())) {
            if ($message instanceof Message) {
                return $this->convertMessageToJob($message, $consumer);
            }
        }

        return null;
    }

    /**
     * Get timeout (in milliseconds).
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
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
            $this->connectionName,
            $this->delayed_exchange
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

    /**
     * Resume consuming when timeout is over.
     *
     * @return bool
     */
    public function shouldResume(): bool
    {
        return $this->resume;
    }

    /**
     * Send message using AMQP producer.
     *
     * @param Producer            $producer
     * @param AmqpTopic|AmqpQueue $destination
     * @param Message             $message
     *
     * @return void
     */
    protected function sendMessage(Producer $producer, $destination, Message $message): void
    {
        $producer->send($destination, $message);
    }
}
