<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Listeners;

use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeCreated;
use Exception;
use Interop\Amqp\Impl\AmqpBind;

/**
 * @see \Enqueue\AmqpTools\RabbitMqDelayPluginDelayStrategy
 * @see <https://github.com/rabbitmq/rabbitmq-delayed-message-exchange>
 */
class CreateExchangeBind extends AbstractExchangeBindListener
{
    /**
     * Handle event.
     *
     * @param ExchangeCreated $event
     */
    public function handle(ExchangeCreated $event): void
    {
        // Try to find created exchange ID into bindings map
        if (! empty($this->bindings_map) && \array_key_exists($event->exchange_id, $this->bindings_map)) {
            try {
                // Create queue instance using queue ID, declared in bindings map
                $queue = $this->queues->make($queue_id = $this->bindings_map[$event->exchange_id]);

                // Says binding rule to the broker
                $event->connection->bind(new AmqpBind($queue, $event->exchange, $queue->getQueueName()));

                // Fire event
                $this->events->dispatch('queue.delayed-jobs.exchange.bind', [
                    'queue_id'    => $queue_id,
                    'exchange_id' => $event->exchange_id,
                ]);
            } catch (Exception $e) {
                $this->handler->report($e);
            }
        }
    }
}
