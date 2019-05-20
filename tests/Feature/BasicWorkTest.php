<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use Interop\Amqp\AmqpQueue;
use AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\SimpleQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\PrioritizedQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobThatThrowsException;

class BasicWorkTest extends AbstractTestCase
{
    /**
     * Indicates if the console output should be mocked.
     *
     * @var bool
     */
    public $mockConsoleOutput = false;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->config();

        $config->set('rabbitmq.connections.testing', [
            'host'     => env('RABBIT_HOST', 'rabbitmq'),
            'port'     => (int) env('RABBIT_PORT', 5672),
            'vhost'    => env('RABBIT_VHOST', '/'),
            'login'    => env('RABBIT_LOGIN', 'guest'),
            'password' => env('RABBIT_PASSWORD', 'guest'),
        ]);

        $config->set('rabbitmq.default_connection', 'testing');

        $config->set('rabbitmq.queues', [
            'jobs'   => [
                'name'         => 'jobs',
                'flags'        => AmqpQueue::FLAG_DURABLE, // Durable queues remain active when a server restarts
                'arguments'    => [
                    'x-max-priority' => 255, // @link <https://www.rabbitmq.com/priority.html>
                ],
                'consumer_tag' => null,
            ],
            'failed' => [
                'name'         => 'failed',
                'flags'        => AmqpQueue::FLAG_DURABLE, // Durable queues remain active when a server restarts
                'arguments'    => [
                    'x-message-ttl' => 604800000, // 7 days (60×60×24×7×1000), @link <https://www.rabbitmq.com/ttl.html>
                    'x-queue-mode'  => 'lazy', // @link <https://www.rabbitmq.com/lazy-queues.html>
                ],
                'consumer_tag' => null,
            ],
        ]);

        $config->set('rabbitmq.setup', [
            'testing' => ['jobs', 'failed'],
        ]);

        $config->set('queue.default', 'rabbitmq');

        $config->set('queue.connections.rabbitmq', [
            'driver'      => ServiceProvider::DRIVER_NAME,
            'connection'  => 'testing',
            'queue_id'    => 'jobs',
            'time_to_run' => 0, // The timeout is in milliseconds
        ]);

        $config->set('queue.failed', [
            'connection' => 'testing',
            'queue_id'   => 'failed',
        ]);

        $this->deleteAllQueues();

        $this->artisan('rabbit:setup', [
            '--recreate' => true,
            '--force'    => true,
        ]);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testPushing(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        dispatch(new SimpleQueueJob);

        $this->artisan('queue:work', ['--once' => true]);

        $output         = $this->console()->output();
        $job_class_safe = \preg_quote(SimpleQueueJob::class, '/');

        $this->assertRegExp('~^\d{2}\:\d{2}\:\d{2}\.\d{3}.+start.+$~im', $output);
        $this->assertRegExp("~^.+processing.+{$job_class_safe}.?$~im", $output);
        $this->assertRegExp("~^.+processed.+{$job_class_safe}.?$~im", $output);

        $this->assertTrue(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));
        $this->assertFalse(Sharer::has(PrioritizedQueueJob::class . '-failed'));
        $this->assertFalse(Sharer::has(QueueJobThatThrowsException::class . '-failed'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testTwoJobs(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(PrioritizedQueueJob::class . '-handled'));

        dispatch(new PrioritizedQueueJob);
        \usleep(3000);
        dispatch(new SimpleQueueJob);

        $this->artisan('queue:work', ['--once' => true]);
        $output1 = $this->console()->output();
        $this->artisan('queue:work', ['--once' => true]);
        $output2 = $this->console()->output();

        $job_class_safe = \preg_quote(PrioritizedQueueJob::class, '/');
        $this->assertRegExp('~^\d{2}\:\d{2}\:\d{2}\.\d{3}.+start.+$~im', $output1);
        $this->assertRegExp("~^.+processing.+{$job_class_safe}.?$~im", $output1);
        $this->assertRegExp("~^.+processed.+{$job_class_safe}.?$~im", $output1);

        $job_class_safe = \preg_quote(SimpleQueueJob::class, '/');
        $this->assertRegExp('~^\d{2}\:\d{2}\:\d{2}\.\d{3}.+start.+$~im', $output2);
        $this->assertRegExp("~^.+processing.+{$job_class_safe}.?$~im", $output2);
        $this->assertRegExp("~^.+processed.+{$job_class_safe}.?$~im", $output2);

        $this->assertTrue(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertTrue(Sharer::has(PrioritizedQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));
        $this->assertFalse(Sharer::has(PrioritizedQueueJob::class . '-failed'));
    }
}
