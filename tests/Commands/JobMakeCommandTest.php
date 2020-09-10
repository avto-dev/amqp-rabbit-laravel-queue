<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Commands;

use Illuminate\Filesystem\Filesystem;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Commands\JobMakeCommand<extended>
 */
class JobMakeCommandTest extends AbstractTestCase
{
    /**
     * Indicates if the console output should be mocked.
     *
     * @var bool
     */
    public $mockConsoleOutput = false;

    /**
     * @var JobMakeCommand
     */
    protected $command;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->command = $this->app->make(JobMakeCommand::class);
        $this->fs      = new Filesystem;
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetStub(): void
    {
        $this->artisan('make:job', [
            'name' => $name = 'Foo',
        ]);

        $this->assertFileExists($file_path = __DIR__ . '/../../vendor/laravel/laravel/app/Jobs/' . $name . '.php');

        require $file_path;

        $this->assertIsObject(new \App\Jobs\Foo);

        $content = \file_get_contents($file_path);

        foreach (['$tries', '$priority', 'strict_types', 'class ' . $name] as $value) {
            $this->assertStringContainsString($value, $content);
        }

        $this->fs->delete($file_path);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetStubSync(): void
    {
        $this->artisan('make:job', [
            'name'   => $name = 'Bar',
            '--sync' => true,
        ]);

        $this->assertFileExists($file_path = __DIR__ . '/../../vendor/laravel/laravel/app/Jobs/' . $name . '.php');

        require $file_path;

        $this->assertIsObject(new \App\Jobs\Bar);

        $content = \file_get_contents($file_path);

        foreach (['$tries', 'strict_types', 'class ' . $name] as $value) {
            $this->assertStringContainsString($value, $content);
        }

        $this->fs->delete($file_path);
    }
}
