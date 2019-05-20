<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Illuminate\Contracts\Queue\Job as JobContract;
use Enqueue\AmqpTools\RabbitMqDelayPluginDelayStrategy;

class Job extends \Illuminate\Queue\Jobs\Job implements JobContract
{
    protected const ATTEMPTS_PROPERTY = 'job-attempts';

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
     * @param Container $container
     * @param Context   $connection
     * @param Consumer  $consumer
     * @param Message   $message
     * @param string    $connection_name
     */
    public function __construct(Container $container,
                                Context $connection,
                                Consumer $consumer,
                                Message $message,
                                $connection_name)
    {
        $this->container      = $container;
        $this->connection     = $connection;
        $this->consumer       = $consumer;
        $this->message        = $message;
        $this->connectionName = $connection_name;
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
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        $requeue_message = clone $this->message;

        $requeue_message->setProperty(self::ATTEMPTS_PROPERTY, $this->attempts() + 1);

        $producer = $this->connection->createProducer();

        if ($delay > 0) {
            $producer->setDelayStrategy(new RabbitMqDelayPluginDelayStrategy);
            $producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        }

        $this->consumer->acknowledge($this->message);

        $producer->send($this->consumer->getQueue(), $requeue_message);
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        return (int) $this->message->getProperty(self::ATTEMPTS_PROPERTY, 1);
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
