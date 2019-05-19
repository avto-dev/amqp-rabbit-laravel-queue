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
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;
use Illuminate\Foundation\Console\JobMakeCommand as IlluminateJobMakeCommand;
use Illuminate\Queue\Connectors\ConnectorInterface as IlluminateQueueConnector;

class ServiceProvider extends IlluminateServiceProvider
{
    public const RABBITMQ_DRIVER_NAME = 'rabbitmq';

    /**
     * Register queue services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->overrideFailedJobService();

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
     * Override 'queue:work' command.
     *
     * @return void
     */
    protected function overrideQueueWorkerCommand(): void
    {
        $this->app->extend(
            'command.queue.work',
            function (IlluminateWorkCommand $original_command, Container $app): IlluminateWorkCommand {
                return new WorkCommand($app->make('queue.worker'));
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
            function (IlluminateJobMakeCommand $original_command, Container $app): IlluminateJobMakeCommand {
                return new JobMakeCommand($app->make('files'));
            }
        );
    }

    /**
     * Bootstrap queue services.
     *
     * @param QueueManager $queue
     *
     * @return void
     */
    public function boot(QueueManager $queue): void
    {
        // Register new driver (connector)
        $queue->addConnector(self::RABBITMQ_DRIVER_NAME, function (): IlluminateQueueConnector {
            /** @var Container $container */
            $container = $this->app;

            return new Connector(
                $container,
                $this->app->make(ConnectionsFactoryInterface::class),
                $this->app->make(QueuesFactoryInterface::class)
            );
        });

        $this->app->extend(
            'queue.worker',
            function (IlluminateWorker $worker, Container $container): IlluminateWorker {
                return new Worker(
                    $container->make(QueueManager::class),
                    $container->make(EventsDispatcher::class),
                    $container->make(ExceptionHandler::class)
                );
            }
        );
    }
}
