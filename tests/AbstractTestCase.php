<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Config\Repository as ConfigRepository;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

abstract class AbstractTestCase extends BaseTestCase
{
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
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__ . '/../vendor/laravel/laravel/bootstrap/app.php';

        // $app->useStoragePath(...);
        // $app->loadEnvironmentFrom(...);

        $app->make(Kernel::class)->bootstrap();

        $app->register(\AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider::class);
        $app->register(\AvtoDev\AmqpRabbitManager\ServiceProvider::class);

        return $app;
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
     * Get console kernel container.
     *
     * @return Kernel
     */
    protected function console(): Kernel
    {
        return $this->app->make(Kernel::class);
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
