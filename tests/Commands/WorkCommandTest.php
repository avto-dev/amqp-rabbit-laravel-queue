<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Commands;

use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Symfony\Component\Console\Input\InputOption;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Commands\WorkCommand
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
        foreach ([\Illuminate\Queue\Console\WorkCommand::class, WorkCommand::class] as $abstract) {
            /** @var \Illuminate\Queue\Console\WorkCommand $command */
            $command = $this->app->make($abstract);

            $reflection = new \ReflectionObject($command);
            $property = $reflection->getProperty('worker');
            $property->setAccessible(true);

            /** @var \Illuminate\Queue\Worker $worker */
            $worker = $property->getValue($command);

            $this->assertInstanceOf(Worker::class, $worker);
        }
    }

    /**
     * @small
     *
     * @return void
     */
    public function testCustomizedCommandOptions(): void
    {
        /** @var WorkCommand $command */
        $command = $this->app->make(\Illuminate\Queue\Console\WorkCommand::class);

        /** @var InputOption[] $options */
        $options = $command->getDefinition()->getOptions();

        $this->assertMatchesRegularExpression('~not used~i', $options['sleep']->getDescription());
        $this->assertEquals(-1, $options['timeout']->getDefault());
    }
}
