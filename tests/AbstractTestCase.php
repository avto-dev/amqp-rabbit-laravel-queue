<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use ReflectionObject;
use ReflectionFunction;
use ReflectionException;
use PHPUnit\Framework\Exception;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\InvalidArgumentException;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Config\Repository as ConfigRepository;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

abstract class AbstractTestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication(): Application
    {
        return require __DIR__ . '/bootstrap/app.php';
    }

    /**
     * {@inheritdoc}
     */
    protected function setUpTraits(): array
    {
        $uses = parent::setUpTraits();

        if (isset($uses[WithTemporaryRabbitConnectionTrait::class])) {
            $this->setUpRabbitConnections();
        }

        return $uses;
    }

    /**
     * Delete all queues for all connections.
     *
     * @return void
     */
    protected function deleteAllQueues(): void
    {
        /** @var ConnectionsFactoryInterface $connections */
        $connections = $this->app->make(ConnectionsFactoryInterface::class);
        /** @var QueuesFactoryInterface $queues */
        $queues = $this->app->make(QueuesFactoryInterface::class);

        // Delete all queues for all connections
        foreach ($connections->names() as $connection_name) {
            $connection = $connections->make($connection_name);

            foreach ($queues->ids() as $id) {
                $queue = $queues->make($id);

                $connection->deleteQueue($queue);
            }
        }
    }

    /**
     * Get app config repository.
     *
     * @return ConfigRepository
     */
    protected function config(): ConfigRepository
    {
        return $this->app->make(ConfigRepository::class);
    }

    /**
     * Get listeners for abstract event.
     *
     * @see https://laravel.com/docs/5.6/events
     * @see https://laravel.com/docs/5.5/events
     *
     * @param mixed|string $event_abstract
     *
     * @throws ReflectionException
     *
     * @return array
     */
    protected function getEventListenersClasses($event_abstract): array
    {
        $result = [];

        foreach (\Illuminate\Support\Facades\Event::getListeners($event_abstract) as $listener_closure) {
            $reflection = new ReflectionFunction($listener_closure);
            $uses       = $reflection->getStaticVariables();

            if (isset($uses['listener'])) {
                $result[] = $uses['listener'];
            }
        }

        return $result;
    }

    /**
     * Mock some property for a object.
     *
     * @param object $object
     * @param string $property_name
     * @param mixed  $value
     *
     * @return void
     */
    protected function mockProperty($object, string $property_name, $value): void
    {
        $reflection = new \ReflectionClass($object);

        $property = $reflection->getProperty($property_name);

        $property->setAccessible(true);
        $property->setValue($object, $value);
        $property->setAccessible(false);
    }

    /**
     * @deprecated
     */
    protected function getObjectAttributeDeprecated($object, string $attributeName)
    {
        $reflector = new ReflectionObject($object);

        do {
            try {
                $attribute = $reflector->getProperty($attributeName);

                if (!$attribute || $attribute->isPublic()) {
                    return $object->$attributeName;
                }

                $attribute->setAccessible(true);
                $value = $attribute->getValue($object);
                $attribute->setAccessible(false);

                return $value;
            } catch (\ReflectionException $e) {
            }
        } while ($reflector = $reflector->getParentClass());

        throw new Exception(
            \sprintf(
                'Attribute "%s" not found in object.',
                $attributeName
            )
        );
    }
}
