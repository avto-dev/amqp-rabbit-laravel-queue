<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Worker as IlluminateWorker;
use AvtoDev\AmqpRabbitManager\QueuesFactoryInterface;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
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
                /** @var ConfigRepository $config_repository */
                $config_repository = $container->make(ConfigRepository::class);
                /** @var array{connection: string|null, queue_id: string|null} $config */
                $config = (array) $config_repository->get('queue.failed');

                if (isset($config['connection'], $config['queue_id'])) {
                    /** @var ConnectionsFactoryInterface $connection_factory */
                    $connection_factory = $container->make(ConnectionsFactoryInterface::class);
                    /** @var QueuesFactoryInterface $queues_factory */
                    $queues_factory = $container->make(QueuesFactoryInterface::class);
                    /** @var ExceptionHandler $exception_handler */
                    $exception_handler = $container->make(ExceptionHandler::class);

                    return new RabbitQueueFailedJobProvider(
                        $connection_factory->make((string) $config['connection']),
                        $queues_factory->make((string) $config['queue_id']),
                        $exception_handler
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

            /** @var QueueFactory $queue_manager */
            $queue_manager = $container->make('queue');
            /** @var Dispatcher $dispatcher */
            $dispatcher = $container->make('events');
            /** @var ExceptionHandler $exception_handler */
            $exception_handler = $container->make(ExceptionHandler::class);

            return new Worker(...[
                $queue_manager,
                $dispatcher,
                $exception_handler,
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
                /** @var IlluminateWorker */
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
            IlluminateWorkCommand::class,
            static function (IlluminateWorkCommand $command, Container $container): IlluminateWorkCommand {
                /** @var IlluminateWorkCommand */
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
            IlluminateJobMakeCommand::class,
            static function (IlluminateJobMakeCommand $command, Container $container): IlluminateJobMakeCommand {
                /** @var IlluminateJobMakeCommand */
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
            /** @var IlluminateQueueConnector */
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
