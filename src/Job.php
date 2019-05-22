<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Enqueue\AmqpExt\AmqpProducer as Producer;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Illuminate\Contracts\Queue\Job as JobContract;

class Job extends \Illuminate\Queue\Jobs\Job implements JobContract
{
    use InteractsWithJobsTrait;

    /**
     * Attempts property name.
     */
    public const ATTEMPTS_PROPERTY = 'job-attempts';

    /**
     * @var AmqpTopic|null
     */
    protected $delayed_exchange;

    /**
     * @var Context
     */
    private $connection;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var Message
     */
    private $message;

    /**
     * Job constructor.
     *
     * @param Container      $container
     * @param Context        $connection
     * @param Consumer       $consumer
     * @param Message        $message
     * @param string         $connection_name
     * @param AmqpTopic|null $delayed_exchange
     */
    public function __construct(Container $container,
                                Context $connection,
                                Consumer $consumer,
                                Message $message,
                                $connection_name,
                                ?AmqpTopic $delayed_exchange = null)
    {
        $this->container        = $container;
        $this->connection       = $connection;
        $this->consumer         = $consumer;
        $this->message          = $message;
        $this->connectionName   = $connection_name;
        $this->delayed_exchange = $delayed_exchange;
    }

    /**
     * {@inheritdoc}
     */
    public function getJobId(): string
    {
        return (string) $this->message->getMessageId();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): void
    {
        parent::delete();

        $this->consumer->acknowledge($this->message);
    }

    /**
     * {@inheritdoc}
     *
     * @param int|float $delay
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        $this->consumer->acknowledge($this->message);

        $requeue_message = clone $this->message;
        $producer        = $this->connection->createProducer();
        $recipient       = $this->consumer->getQueue(); // Default way

        $requeue_message->setProperty(self::ATTEMPTS_PROPERTY, $this->attempts() + 1);

        // If delayed exchange are defined and message should be sent with delay - change the recipient
        if ($delay > 0 && $this->delayed_exchange instanceof AmqpTopic) {
            $requeue_message->setProperty('x-delay', $this->delayToMilliseconds($delay));
            $requeue_message->setRoutingKey($this->consumer->getQueue()->getQueueName());

            $recipient = $this->delayed_exchange;
        }

        $this->sendMessage($producer, $recipient, $requeue_message);
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        return (int) $this->message->getProperty(self::ATTEMPTS_PROPERTY, 1);
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

    /**
     * {@inheritdoc}
     */
    public function getQueue(): string
    {
        return $this->consumer->getQueue()->getQueueName();
    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    /**
     * @return Message
     */
    public function getMessage(): Message
    {
        return $this->message;
    }
}
