<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Illuminate\Queue\QueueManager;
use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Queue\Events\JobProcessing;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeCreated;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeDeleting;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\CreateExchangeBind;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\RemoveExchangeBind;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\BindJobStateListener;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider<extended>
 */
class ServiceProviderTest extends AbstractTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var bool
     */
    public $disable_rabbitmq_temporary = true;

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
    public function testQueueDriverRegistration(): void
    {
        $this->assertArrayHasKey(
            Connector::NAME,
            $this->getObjectAttributeDeprecated($this->app->make(QueueManager::class), 'connectors')
        );
    }

    /**
     * @small
     *
     * @return void
     */
    public function testListenersBooting(): void
    {
        $this->assertContains(CreateExchangeBind::class, $this->getEventListenersClasses(ExchangeCreated::class));
        $this->assertContains(RemoveExchangeBind::class, $this->getEventListenersClasses(ExchangeDeleting::class));
        $this->assertContains(BindJobStateListener::class, $this->getEventListenersClasses(JobProcessing::class));
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
    public function testFallbackFailedJobService(): void
    {
        $this->config()->offsetUnset('queue.failed.connection');
        $this->config()->offsetUnset('queue.failed.queue_id');

        $this->assertNotInstanceOf(RabbitQueueFailedJobProvider::class, $this->app->make('queue.failer'));
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

        $connector = $this->getObjectAttributeDeprecated($queue, 'connectors')[Connector::NAME];

        $this->assertInstanceOf(Connector::class, $connector());
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
    public function testOverrideMakeJobCommand(): void
    {
        $this->assertInstanceOf(JobMakeCommand::class, $this->app->make('command.job.make'));
    }
}
