<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Commands;

class JobMakeCommand extends \Illuminate\Foundation\Console\JobMakeCommand
{
    /**
     * {@inheritdoc}
     */
    protected function getStub(): string
    {
        return $this->option('sync')
            ? __DIR__ . '/stubs/job.stub'
            : __DIR__ . '/stubs/job-queued.stub';
    }
}
