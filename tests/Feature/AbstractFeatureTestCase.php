<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Illuminate\Contracts\Bus\Dispatcher;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider;

abstract class AbstractFeatureTestCase extends AbstractTestCase
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
     * @var int Test starting timestamp
     */
    protected $now;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        Sharer::clear();

        $this->fs = new Filesystem;

        if (! $this->fs->isDirectory(__DIR__ . '/../../vendor/laravel/laravel/config.orig')) {
            $this->fs->copyDirectory(
                __DIR__ . '/../../vendor/laravel/laravel/config',
                __DIR__ . '/../../vendor/laravel/laravel/config.orig'
            );
        }

        $this->fs->copyDirectory(
            __DIR__ . '/../config',
            __DIR__ . '/../../vendor/laravel/laravel/config'
        );

        $this->refreshApplication();

        $this->dispatcher = $this->app->make(Dispatcher::class);

        $this->artisan('rabbit:setup', [
            '--recreate' => true,
            '--force'    => true,
        ]);

        $this->failer = $this->app->make('queue.failer');

        $this->now = (new \DateTime)->getTimestamp();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->fs->deleteDirectory(__DIR__ . '/../../vendor/laravel/laravel/config');

        $this->fs->moveDirectory(
            __DIR__ . '/../../vendor/laravel/laravel/config.orig',
            __DIR__ . '/../../vendor/laravel/laravel/config'
        );

        Sharer::clear();

        parent::tearDown();
    }

    /**
     * @param string     $command
     * @param array      $arguments
     * @param float|null $process_timeout
     * @param array|null $env
     *
     * @return array
     */
    protected function startArtisan(string $command,
                                    array $arguments = [],
                                    ?float $process_timeout = null,
                                    ?array $env = null): array
    {
        $process_timeout = (float) env('ARTISAN_PROCESS_TIMEOUT', $process_timeout ?? 0.65);

        $standard_output = new CommandOutput;
        $errors_output   = new CommandOutput;
        $timed_out       = false;

        $process = new Process(\array_merge([
            PHP_BINARY,
            \realpath(__DIR__ . '/../bin/artisan'),
            $command,
            '--no-ansi',
        ], $arguments), base_path(), $env, null, $process_timeout);

        $lineToArray = function (string $line): array {
            $lines = \preg_split("~(\r|\n)~", $line);

            return \array_filter($lines, function ($line) {
                $line = \trim($line);

                return ! empty($line)
                    ? $line
                    : null;
            });
        };

        try {
            $process->run(function ($type, $line) use ($standard_output, $errors_output, $lineToArray): void {
                if ($type === Process::ERR) {
                    foreach ($lineToArray($line) as $single_line) {
                        $errors_output->push($single_line);
                    }
                } else {
                    foreach ($lineToArray($line) as $single_line) {
                        $standard_output->push($single_line);
                    }
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
