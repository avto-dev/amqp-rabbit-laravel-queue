<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Illuminate\Queue\QueueManager;
use Illuminate\Container\Container;
use Illuminate\Queue\Worker as IlluminateWorker;
use Illuminate\Contracts\Debug\ExceptionHandler;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeCreated;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeDeleting;
use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;
use Illuminate\Foundation\Console\JobMakeCommand as IlluminateJobMakeCommand;
use Illuminate\Queue\Connectors\ConnectorInterface as IlluminateQueueConnector;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register queue services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->overrideFailedJobService();
        $this->overrideQueueWorker();

        if ($this->app->runningInConsole()) {
            $this->overrideQueueWorkerCommand();
            $this->overrideMakeJobCommand();
        }
    }

    /**
     * Register the failed job service.
     *
     * @return void
     */
    protected function overrideFailedJobService(): void
    {
        $this->app->extend(
            'queue.failer',
            function ($original_service, Container $container): FailedJobProviderInterface {
                $config = (array) $container->make(ConfigRepository::class)->get('queue.failed');

                if (isset($config['connection'], $config['queue_id'])) {
                    return new RabbitQueueFailedJobProvider(
                        $container->make(ConnectionsFactoryInterface::class)->make((string) $config['connection']),
                        $container->make(QueuesFactoryInterface::class)->make((string) $config['queue_id']),
                        $container->make(ExceptionHandler::class)
                    );
                }

                return $original_service;
            }
        );
    }

    /**
     * Override original queue worker.
     *
     * @return void
     */
    protected function overrideQueueWorker(): void
    {
        $this->app->extend(
            'queue.worker',
            function (IlluminateWorker $worker, Container $container): IlluminateWorker {
                return $container->make(Worker::class);
            }
        );
    }

    /**
     * Override 'queue:work' command.
     *
     * @return void
     */
    protected function overrideQueueWorkerCommand(): void
    {
        $this->app->extend(
            'command.queue.work',
            function (IlluminateWorkCommand $original_command, Container $container): IlluminateWorkCommand {
                return $container->make(WorkCommand::class);
            }
        );
    }

    /**
     * Override 'make:job' command.
     *
     * @return void
     */
    protected function overrideMakeJobCommand(): void
    {
        $this->app->extend(
            'command.job.make',
            function (IlluminateJobMakeCommand $original_command, Container $container): IlluminateJobMakeCommand {
                return $container->make(JobMakeCommand::class);
            }
        );
    }

    /**
     * Bootstrap package services.
     *
     * @param QueueManager     $queue
     * @param EventsDispatcher $events
     *
     * @return void
     */
    public function boot(QueueManager $queue, EventsDispatcher $events): void
    {
        $this->bootQueueDriver($queue);
        $this->bootListeners($events);
    }

    /**
     * Register new driver (connector)
     *
     * @param QueueManager $queue
     *
     * @return void
     */
    protected function bootQueueDriver(
        $queue): void
    {
        $queue->addConnector(Connector::NAME, function (): IlluminateQueueConnector {
            return $this->app->make(Connector::class);
        });
    }

    /**
     * Boot up package listeners.
     *
     * @param EventsDispatcher $events
     *
     * @return void
     */
    protected function bootListeners(EventsDispatcher $events): void
    {
        $events->listen(ExchangeCreated::class, Listeners\CreateExchangeBind::class);
        $events->listen(ExchangeDeleting::class, Listeners\RemoveExchangeBind::class);
    }
}
