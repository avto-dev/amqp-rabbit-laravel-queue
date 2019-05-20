<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;
use AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;
use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\QueueManager;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider<extended>
 */
class ServiceProviderTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @small
     *
     * @return void
     */
    public function testConstants(): void
    {
        $this->assertSame('rabbitmq', ServiceProvider::DRIVER_NAME);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOverrideMakeJobCommand(): void
    {
        $this->assertInstanceOf(JobMakeCommand::class, $this->app->make('command.job.make'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOverrideQueueWorkerCommand(): void
    {
        $this->assertInstanceOf(WorkCommand::class, $this->app->make('command.queue.work'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOverrideFailedJobServiceWhenSettingsCorrect(): void
    {
        $this->config()->set('queue.failed.connection', $this->temp_rabbit_connection_name);
        $this->config()->set('queue.failed.queue_id', $this->temp_rabbit_queue_id);

        $this->assertInstanceOf(RabbitQueueFailedJobProvider::class, $this->app->make('queue.failer'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testFailbackFailedJobService(): void
    {
        $this->config()->offsetUnset('queue.failed.connection');
        $this->config()->offsetUnset('queue.failed.queue_id');

        $this->assertInstanceOf(DatabaseFailedJobProvider::class, $this->app->make('queue.failer'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testQueueWorkerExtending(): void
    {
        $this->assertInstanceOf(Worker::class, $this->app->make('queue.worker'));
    }

    /**
     * @small
     *
     * @return void
     */
    public function testQueueConnectorExists(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app->make(QueueManager::class);

        $connector = $this->getObjectAttribute($queue, 'connectors')[ServiceProvider::DRIVER_NAME];

        $this->assertInstanceOf(Connector::class, $connector());
    }
}
