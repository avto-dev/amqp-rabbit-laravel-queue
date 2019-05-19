<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Enqueue\AmqpExt\AmqpContext;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;

/**
 * @property \Illuminate\Foundation\Application app
 * @property callable[]                         beforeApplicationDestroyedCallbacks
 * @property bool                               disable_rabbitmq_temporary
 * @property bool                               disable_rabbitmq_queue_creation
 */
trait WithTemporaryRabbitConnectionTrait
{
    /**
     * @var string
     */
    protected $temp_rabbit_connection_name;

    /**
     * @var AmqpContext
     */
    protected $temp_rabbit_connection;

    /**
     * @var string
     */
    protected $temp_rabbit_queue_id;

    /**
     * @var string
     */
    protected $temp_rabbit_queue_name;

    /**
     * @var AmqpQueue
     */
    protected $temp_rabbit_queue;

    /**
     * @return void
     */
    protected function setUpRabbitConnections(): void
    {
        $disable_rabbitmq_temporary = \property_exists($this, 'disable_rabbitmq_temporary')
                                      && $this->disable_rabbitmq_temporary === true;

        /** @var ConnectionsFactoryInterface $connections */
        $connections = $this->app->make(ConnectionsFactoryInterface::class);

        /** @var QueuesFactoryInterface $queues */
        $queues = $this->app->make(QueuesFactoryInterface::class);

        // Generate random names & IDs
        $this->temp_rabbit_connection_name = 'temp-rabbit-connection-' . Str::random();
        $this->temp_rabbit_queue_id        = 'temp-queue-id-' . Str::random();
        $this->temp_rabbit_queue_name      = 'temp-queue-name-' . Str::random();

        // Register connection factory
        $connections->addFactory($this->temp_rabbit_connection_name, [
            'host'     => env('TEST_RABBIT_HOST', env('RABBIT_HOST', 'rabbitmq')),
            'port'     => (int) env('TEST_RABBIT_PORT', env('RABBIT_PORT', 5672)),
            'vhost'    => env('TEST_RABBIT_VHOST', env('RABBIT_VHOST', '/')),
            'login'    => env('TEST_RABBIT_LOGIN', env('RABBIT_LOGIN', 'guest')),
            'password' => env('TEST_RABBIT_PASSWORD', env('RABBIT_PASSWORD', 'guest')),
        ]);

        // Register queue factory
        $queues->addFactory($this->temp_rabbit_queue_id, [
            'name'         => $this->temp_rabbit_queue_name,
            'flags'        => AmqpQueue::FLAG_NOPARAM,
            'arguments'    => [
                'x-max-priority' => 255, // @link <https://www.rabbitmq.com/priority.html>
                'x-max-length'   => 1024 * 8, // @link <https://www.rabbitmq.com/maxlength.html>
                'x-expires'      => 60 * 1 * 1000, // 1 min, @link <https://www.rabbitmq.com/ttl.html>
            ],
            'consumer_tag' => null,
        ]);

        if ($disable_rabbitmq_temporary !== true) {
            // Create connection
            $this->temp_rabbit_connection = $connections->make($this->temp_rabbit_connection_name);

            // Create temp queue
            $this->temp_rabbit_queue = $queues->make($this->temp_rabbit_queue_id);

            // Create queue on broker
            $this->temp_rabbit_connection->declareQueue($this->temp_rabbit_queue);
        }

        // Register destroy callback
        $this->beforeApplicationDestroyedCallbacks[] = function () use ($connections, $queues): void {
            if ($this->temp_rabbit_connection instanceof AmqpContext) {
                if ($this->temp_rabbit_queue instanceof AmqpQueue) {
                    $this->temp_rabbit_connection->deleteQueue($this->temp_rabbit_queue);
                }

                $this->temp_rabbit_connection->close();
            }

            $connections->removeFactory($this->temp_rabbit_connection_name);
            $queues->removeFactory($this->temp_rabbit_queue_id);

            unset(
                $this->temp_rabbit_connection_name,
                $this->temp_rabbit_queue_id,
                $this->temp_rabbit_queue_name,
                $this->temp_rabbit_queue,
                $this->temp_rabbit_connection
            );
        };
    }
}
