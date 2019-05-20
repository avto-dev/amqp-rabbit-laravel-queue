<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository as ConfigRepository;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
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
    protected function setUp(): void
    {
        Sharer::clear();

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        Sharer::clear();
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
}
