<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Commands;

use Illuminate\Contracts\Queue\Job;

/**
 * You should NOT register this command in console kernel.
 *
 * @see \App\Providers\RabbitQueueServiceProvider::overrideQueueWorkerCommand
 */
class WorkCommand extends \Illuminate\Queue\Console\WorkCommand
{
    /**
     * {@inheritdoc}
     */
    public function line($string, $style = null, $verbosity = null): void
    {
        $time = (new \DateTime)->format('H:i:s.v');

        $styled = $style
            ? "<$style>$string</$style>"
            : $string;

        $this->output->writeln("<fg=white>{$time}</> {$styled}", $this->parseVerbosity($verbosity));
    }

    /**
     * {@inheritdoc}
     */
    protected function writeStatus(Job $job, $status, $type): void
    {
        $this->line(sprintf(
            "<{$type}>[%s] %s</{$type}> %s",
            \method_exists($job, $method_name = 'getJobId')
                ? $job->{$method_name}()
                : null,
            str_pad("{$status}:", 11), $job->resolveName()
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function runWorker($connection, $queue)
    {
        $this->info('Queue worker started. Press "CTRL+C" to exit');

        return parent::runWorker($connection, $queue);
    }
}
