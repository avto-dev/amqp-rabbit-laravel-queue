<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Illuminate\Contracts\Bus\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;

abstract class AbstractFeatureTest extends AbstractTestCase
{
    /**
     * @var RabbitQueueFailedJobProvider
     */
    protected $failer;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new Filesystem;

        if (! $this->fs->isDirectory(__DIR__ . '/../../vendor/laravel/laravel/config.orig')) {
            $this->fs->copyDirectory(
                __DIR__ . '/../../vendor/laravel/laravel/config',
                __DIR__ . '/../../vendor/laravel/laravel/config.orig'
            );
        }

        $this->fs->copyDirectory(
            __DIR__ . '/config',
            __DIR__ . '/../../vendor/laravel/laravel/config'
        );

        $this->refreshApplication();

        $this->dispatcher = $this->app->make(Dispatcher::class);

        $this->artisan('rabbit:setup', [
            '--recreate' => true,
            '--force'    => true,
        ]);

        $this->failer = $this->app->make('queue.failer');
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        $this->fs->deleteDirectory(__DIR__ . '/../../vendor/laravel/laravel/config');

        $this->fs->moveDirectory(
            __DIR__ . '/../../vendor/laravel/laravel/config.orig',
            __DIR__ . '/../../vendor/laravel/laravel/config'
        );

        parent::tearDown();
    }

    /**
     * @param string $command
     * @param array  $arguments
     * @param float  $process_timeout
     *
     * @return array
     */
    protected function startArtisan(string $command,
                                    array $arguments = [],
                                    float $process_timeout = 0.65): array
    {
        $standard_output = new Collection;
        $errors_output   = new Collection;
        $timed_out       = false;

        $process = new Process(\array_merge([
            PHP_BINARY,
            \realpath(__DIR__ . '/../bin/artisan'),
            $command,
            '--no-ansi',
        ], $arguments), base_path(), null, null, $process_timeout);

        try {
            $process->run(function ($type, $line) use ($standard_output, $errors_output): void {
                if ($type === Process::ERR) {
                    $errors_output->push($line);
                } else {
                    $standard_output->push($line);
                }
            });
        } catch (ProcessTimedOutException $e) {
            $timed_out = true;
        }

        return [
            'process'   => $process,
            'stdout'    => $standard_output,
            'stderr'    => $errors_output,
            'timed_out' => $timed_out,
        ];
    }
}
