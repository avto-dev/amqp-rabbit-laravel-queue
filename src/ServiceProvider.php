<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Worker as IlluminateWorker;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitManager\ConnectionsFactoryInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeCreated;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeDeleting;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
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
        $this->registerQueueWorker();
        $this->overrideQueueWorker();

        if ($this->app->runningInConsole()) {
            $this->overrideQueueWorkerCommand();
            $this->overrideMakeJobCommand();
        }
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
     * Register the failed job service.
     *
     * @return void
     */
    protected function overrideFailedJobService(): void
    {
        $this->app->extend(
            'queue.failer',
            static function ($original_service, Container $container): FailedJobProviderInterface {
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
     * Register our queue worker.
     *
     * @return void
     */
    protected function registerQueueWorker(): void
    {
        $this->app->singleton(Worker::class, function (Container $container): Worker {
            // Constructor signature changed since 6.0:
            // For ~5.5 three arguments:
            // - https://github.com/illuminate/queue/blob/5.5/Worker.php#L68
            // - https://github.com/illuminate/queue/blob/5.6/Worker.php#L68
            // - https://github.com/illuminate/queue/blob/5.7/Worker.php#L68
            // - https://github.com/illuminate/queue/blob/5.8/Worker.php#L68
            // Since ^6.0 - four arguments:
            // - https://github.com/illuminate/queue/blob/6.x/Worker.php#L82
            // - https://github.com/illuminate/queue/blob/7.x/Worker.php#L80
            return new Worker(...[
                $container->make('queue'),
                $container->make('events'),
                $container->make(ExceptionHandler::class),
                function (): bool { // Required since illuminate/queue ^6.0
                    return $this->app->isDownForMaintenance();
                },
            ]);
        });
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
            static function (IlluminateWorker $worker, Container $container): IlluminateWorker {
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
            static function (IlluminateWorkCommand $command, Container $container): IlluminateWorkCommand {
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
            static function (IlluminateJobMakeCommand $command, Container $container): IlluminateJobMakeCommand {
                return $container->make(JobMakeCommand::class);
            }
        );
    }

    /**
     * Register new driver (connector).
     *
     * @param QueueManager $queue
     *
     * @return void
     */
    protected function bootQueueDriver(QueueManager $queue): void
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
        $events->listen(JobProcessing::class, Listeners\BindJobStateListener::class);
    }
}
