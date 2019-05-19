<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Config\Repository as ConfigRepository;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class AbstractTestCase extends BaseTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ServiceProvider::class);
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

        $app->register(ServiceProvider::class);

        return $app;
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
}
