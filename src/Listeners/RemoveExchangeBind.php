<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Listeners;

use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeDeleting;
use Exception;
use Interop\Amqp\Impl\AmqpBind;

class RemoveExchangeBind extends AbstractExchangeBindListener
{
    /**
     * Handle event.
     *
     * @param ExchangeDeleting $event
     */
    public function handle(ExchangeDeleting $event): void
    {
        // Try to find created exchange ID into bindings map
        if (! empty($this->bindings_map) && \array_key_exists($event->exchange_id, $this->bindings_map)) {
            try {
                // Create queue instance using queue ID, declared in bindings map
                $queue = $this->queues->make($queue_id = $this->bindings_map[$event->exchange_id]);

                // Says broker - destroy binding rule
                $event->connection->unbind(new AmqpBind($queue, $event->exchange, $queue->getQueueName()));

                // Fire event
                $this->events->dispatch('queue.delayed-jobs.exchange.unbind', [
                    'queue_id'    => $queue_id,
                    'exchange_id' => $event->exchange_id,
                ]);
            } catch (Exception $e) {
                $this->handler->report($e);
            }
        }
    }
}
