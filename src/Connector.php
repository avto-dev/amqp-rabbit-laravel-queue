<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use InvalidArgumentException;
use Illuminate\Container\Container;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;

class Connector implements \Illuminate\Queue\Connectors\ConnectorInterface
{
    /**
     * @var ConnectionsFactoryInterface
     */
    protected $rabbit_mq_manager;

    /**
     * @var QueuesFactoryInterface
     */
    protected $queues_factory;

    /**
     * @var Container
     */
    protected $container;

    /**
     * Connector constructor.
     *
     * @param Container                   $container
     * @param ConnectionsFactoryInterface $rabbit_mq_manager
     * @param QueuesFactoryInterface      $queues_factory
     */
    public function __construct(Container $container,
                                ConnectionsFactoryInterface $rabbit_mq_manager,
                                QueuesFactoryInterface $queues_factory)
    {
        $this->container         = $container;
        $this->rabbit_mq_manager = $rabbit_mq_manager;
        $this->queues_factory    = $queues_factory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function connect(array $config): QueueContract
    {
        if (! isset($config['connection'])) {
            throw new InvalidArgumentException('RabbitMQ connection name was not passed');
        }

        if (! isset($config['queue_id'])) {
            throw new InvalidArgumentException('RabbitMQ queue ID was not passed');
        }

        $connection = $this->rabbit_mq_manager->make($config['connection']);
        $queue      = $this->queues_factory->make($config['queue_id']);
        $timeout    = (int) ($config['timeout'] ?? 0);

        return new Queue($this->container, $connection, $queue, $timeout);
    }
}
