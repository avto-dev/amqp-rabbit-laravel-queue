<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Commands;

use AvtoDev\AmqpRabbitLaravelQueue\Worker;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * You should NOT register this command in console kernel.
 *
 * @see \AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider::overrideQueueWorkerCommand()
 */
class WorkCommand extends \Illuminate\Queue\Console\WorkCommand
{
    /**
     * Create a new queue work command.
     *
     * @param Worker $worker
     * @param Cache  $cache
     */
    public function __construct(Worker $worker, Cache $cache)
    {
        // Override default timeout value ('60' to '-1')
        $this->signature = (string) \preg_replace(
            '~(--timeout=)\d+~', '$1-1', $this->signature
        );

        // Mark 'sleep' option as not used
        $this->signature = (string) \preg_replace(
            '~(--sleep.*)}~', '$1 <options=bold>(not used)</> }', $this->signature
        );

        parent::__construct($worker, $cache);
    }

    /**
     * Run the worker instance.
     *
     * @inheritdoc
     *
     * @codeCoverageIgnore
     */
    protected function runWorker($connection, $queue)
    {
        $this->info('Queue worker started. Press "CTRL+C" to exit');

        return parent::runWorker($connection, $queue);
    }
}
