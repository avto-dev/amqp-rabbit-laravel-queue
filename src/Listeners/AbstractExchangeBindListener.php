<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Listeners;

use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

abstract class AbstractExchangeBindListener
{
    /**
     * @var QueuesFactoryInterface
     */
    protected $queues;

    /**
     * @var ExceptionHandler
     */
    protected $handler;

    /**
     * @var EventsDispatcher
     */
    protected $events;

    /**
     * Array KEY is delayed jobs exchange ID, and its VALUE is jobs queue ID.
     *
     * @var array<string, string>
     */
    protected $bindings_map;

    /**
     * @var ConfigRepository
     */
    protected $config;

    /**
     * Create listener instance.
     *
     * @param QueuesFactoryInterface $queues
     * @param ExceptionHandler       $handler
     * @param EventsDispatcher       $events
     * @param ConfigRepository       $config
     */
    public function __construct(QueuesFactoryInterface $queues,
                                ExceptionHandler $handler,
                                EventsDispatcher $events,
                                ConfigRepository $config)
    {
        $this->queues  = $queues;
        $this->handler = $handler;
        $this->events  = $events;
        $this->config  = $config;

        $this->bindings_map = $this->getBindingsMap();
    }

    /**
     * Returns exchanges => queues bindings map.
     *
     * @param string $key
     *
     * @return array<string, string>
     */
    protected function getBindingsMap(string $key = 'queue.connections'): array
    {
        $map = [];

        /** @var array{string, array{driver: string|null, queue_id: string|null, delayed_exchange_id: string|null,}} $connections */
        $connections = (array) $this->config->get($key);

        // Fill bindings map
        foreach ($connections as $name => $settings) {
            // Walk thought 'queue.connections.*' sections
            if (($settings['driver'] ?? null) === Connector::NAME) {
                $queue_id            = $settings['queue_id'] ?? null;
                $delayed_exchange_id = $settings['delayed_exchange_id'] ?? null;

                if (\is_string($queue_id) && \is_string($delayed_exchange_id)) {
                    $map[$delayed_exchange_id] = $queue_id;
                }
            }
        }

        return $map;
    }
}
