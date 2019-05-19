<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue;

use Throwable;
use Illuminate\Queue\WorkerOptions;
use Interop\Amqp\AmqpMessage as Message;
use Enqueue\AmqpExt\AmqpContext as Context;
use Enqueue\AmqpExt\AmqpConsumer as Consumer;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class Worker extends \Illuminate\Queue\Worker
{
    /**
     * {@inheritdoc}
     */
    public function daemon($connectionName, $queue_names, WorkerOptions $options): void
    {
        $current_queue = $this->manager->connection($connectionName);

        // This worker should work with RabbitMQ queue, or not?
        if ($current_queue instanceof Queue) {
            if ($this->supportsAsyncSignals()) {
                $this->listenForSignals();
            }

            $lastRestart       = $this->getTimestampOfLastQueueRestart();
            $rabbit_connection = $current_queue->getRabbitConnection();
            $rabbit_queue      = $current_queue->getRabbitQueue();

            $consumer   = $rabbit_connection->createConsumer($rabbit_queue);
            $subscriber = $rabbit_connection->createSubscriptionConsumer();

            $subscriber->subscribe(
                $consumer,
                function (Message $message, Consumer $consumer) use (
                    $current_queue,
                    $connectionName,
                    $queue_names,
                    $options,
                    $lastRestart
                ): bool {
                    // Before reserving any jobs, we will make sure this queue is not paused and
                    // if it is we will just pause this worker for a given amount of time and
                    // make sure we do not need to kill this worker process off completely.
                    if (! $this->daemonShouldRun($options, $connectionName, $queue_names)) {
                        $consumer->reject($message);

                        $this->pauseWorker($options, $lastRestart);

                        return true;
                    }

                    // Make job instance, based on incoming message
                    try {
                        $job = $current_queue->convertMessageToJob($message, $consumer);
                    } catch (Throwable $e) {
                        $consumer->reject($message); // @todo: move to the failed jobs queue?

                        $this->exceptions->report($e = new FatalThrowableError($e));
                        $this->stopWorkerIfLostConnection($e);
                        $this->sleep(0.2);

                        return true;
                    }

                    if ($this->supportsAsyncSignals()) {
                        $this->registerTimeoutHandler($job, $options);
                    }

                    declare(ticks = 100) {
                        $this->runJob($job, $connectionName, $options);
                    }

                    // Finally, we will check to see if we have exceeded our memory limits or if
                    // the queue should restart based on other indications. If so, we'll stop
                    // this worker and let whatever is "monitoring" it restart the process.
                    if ($this->needToStop($options, $lastRestart, $job)) {
                        return false;
                    }

                    return true;
                }
            );

            $subscriber->consume($current_queue->getTimeToRun()); // Start `subscribe` method loop

            $subscriber->unsubscribe($consumer);
            $this->closeRabbitConnection($rabbit_connection);

            $this->stop();
        // @codeCoverageIgnoreStart
        } else {
            // Backward compatibility is our everything =)
            parent::daemon($connectionName, $queue_names, $options);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine if process stopping is needed.
     *
     * IMPORTANT: All cases should be compatible with `stopIfNecessary(...)` method!
     *
     * @param WorkerOptions $options
     * @param int           $lastRestart
     * @param mixed         $job
     *
     * @return bool
     */
    protected function needToStop(WorkerOptions $options, $lastRestart, $job = null): bool
    {
        switch (true) {
            case $this->shouldQuit:
            case $this->memoryExceeded($options->memory):
            case $this->queueShouldRestart($lastRestart):
            case $options->stopWhenEmpty && $job === null:
                return true;
        }

        return false;
    }

    /**
     * Close rabbit connection.
     *
     * @param Context $connection
     */
    protected function closeRabbitConnection(Context $connection): void
    {
        $connection->close();
    }

    /**
     * {@inheritdoc}
     */
    protected function causedByLostConnection(Throwable $e): bool
    {
        switch (true) {
            case $e instanceof \AMQPExchangeException:
            case $e instanceof \AMQPConnectionException:
                return true;
        }

        return parent::causedByLostConnection($e);
    }
}
