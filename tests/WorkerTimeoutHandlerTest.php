<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Mockery as m;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Queue\Worker as IlluminateWorker;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Worker
 *
 * @group queue
 */
class WorkerTimeoutHandlerTest extends AbstractTestCase
{
    /**
     * @small
     *
     * @return void
     */
    public function testRegisterTimeoutHandlerForLaravelMatchesParentArity(): void
    {
        $parameter_count = (new \ReflectionMethod(
            IlluminateWorker::class,
            'registerTimeoutHandler'
        ))->getNumberOfParameters();

        $connection_name = 'rabbit-connection';
        $queue           = 'default';
        $job             = m::mock(JobContract::class);
        $options         = new WorkerOptions();

        $worker = m::mock(Worker::class, [
            $this->app->make(QueueManager::class),
            $this->app->make(EventsDispatcher::class),
            $this->app->make(ExceptionHandler::class),
            static function () {
            },
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects('registerTimeoutHandler')
            ->once()
            ->withArgs(function (...$args) use ($parameter_count, $connection_name, $queue, $job, $options): bool {
                $this->assertCount($parameter_count, $args);

                if ($parameter_count >= 4) {
                    $this->assertSame($connection_name, $args[0]);
                    $this->assertSame($queue, $args[1]);
                    $this->assertSame($job, $args[2]);
                    $this->assertSame($options, $args[3]);
                } else {
                    $this->assertSame($job, $args[0]);
                    $this->assertSame($options, $args[1]);
                }

                return true;
            })
            ->getMock();

        $method = new \ReflectionMethod(Worker::class, 'registerTimeoutHandlerCompatible');
        $method->setAccessible(true);
        $method->invoke($worker, $connection_name, $queue, $job, $options);
    }
}
