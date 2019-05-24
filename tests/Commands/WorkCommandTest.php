<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Commands;

use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand<extended>
 */
class WorkCommandTest extends AbstractTestCase
{
    /**
     * @small
     *
     * @return void
     */
    public function testCorrectWorkerPasses(): void
    {
        foreach (['command.queue.work', WorkCommand::class] as $abstract) {
            /** @var \Illuminate\Queue\Console\WorkCommand $command */
            $command = $this->app->make($abstract);

            /** @var \Illuminate\Queue\Worker $worker */
            $worker = $this->getObjectAttribute($command, 'worker');

            $this->assertInstanceOf(Worker::class, $worker);
        }
    }
}
