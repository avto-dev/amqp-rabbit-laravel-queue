<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use Illuminate\Support\Collection;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\SimpleQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\PrioritizedQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobThatThrowsException;

class QueueWorkerTest extends AbstractFeatureTest
{
    /**
     * Indicates if the console output should be mocked.
     *
     * @var bool
     */
    public $mockConsoleOutput = false;

    /**
     * @medium
     *
     * @return void
     */
    public function testPushing(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out']);
        /** @var Collection $output */
        $output         = $process_info['stdout'];
        $output_single  = \implode("\n", $output->all());
        $job_class_safe = \preg_quote(SimpleQueueJob::class, '/');

        $this->assertEmpty($process_info['stderr']);
        $this->assertRegExp('~^\d{2}\:\d{2}\:\d{2}\.\d{3}.+start.+$~im', $output[0], $output_single);
        $this->assertRegExp("~^.+processing.+{$job_class_safe}.?$~im", $output[1], $output_single);
        $this->assertRegExp("~^.+processed.+{$job_class_safe}.?$~im", $output[2], $output_single);
        $this->assertSame(1, Sharer::get(SimpleQueueJob::class . '-handled'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testTwoJobs(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        dispatch(new SimpleQueueJob);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out']);
        /** @var Collection $output */
        $output        = $process_info['stdout'];
        $output_single = \implode("\n", $output->all());

        $this->assertGreaterThanOrEqual(5, $output->count(), $output_single);
        $this->assertSame(2, Sharer::get(SimpleQueueJob::class . '-handled'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testWithPrioritizedJob(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(PrioritizedQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch(new PrioritizedQueueJob);
        $this->dispatcher->dispatch(new SimpleQueueJob);

        \usleep(1000);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out']);
        /** @var Collection $output */
        $output        = $process_info['stdout'];
        $output_single = \implode("\n", $output->all());

        // Should be processed FIRST
        $this->assertRegExp('~' . \preg_quote(PrioritizedQueueJob::class, '/') . '$~i', $output[1], $output_single);

        $this->assertGreaterThanOrEqual(9, $output->count(), $output_single);
        $this->assertSame(3, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertSame(1, Sharer::get(PrioritizedQueueJob::class . '-handled'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testJobFailing(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(QueueJobThatThrowsException::class . '-handled'));
        $this->assertFalse(Sharer::has(QueueJobThatThrowsException::class . '-throws'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch($will_throws = new QueueJobThatThrowsException);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out']);
        /** @var Collection $output */
        $output        = $process_info['stdout'];
        $output_single = \implode("\n", $output->all());

        $this->assertGreaterThanOrEqual(6, $output->count(), $output_single);
        $this->assertSame(1, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertSame($will_throws->getTries(), Sharer::get(QueueJobThatThrowsException::class . '-throws'));
        $this->assertSame(1, Sharer::get(QueueJobThatThrowsException::class . '-failed'));

        $this->assertSame(1, $this->failer->count());
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testRetryFailedJob(): void
    {
        $this->dispatcher->dispatch($will_throws = new QueueJobThatThrowsException);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out']);

        $this->assertSame($will_throws->getTries(), Sharer::get(QueueJobThatThrowsException::class . '-throws'));
        $this->assertSame(1, Sharer::get(QueueJobThatThrowsException::class . '-failed'));

        $this->assertSame(1, $this->failer->count());
        $failed_job_id = $this->failer->all()[0]->id;

        $process_info = $this->startArtisan('queue:retry', ['all']);

        $this->assertFalse($process_info['timed_out']);
        /** @var Collection $output */
        $output        = $process_info['stdout'];
        $output_single = \implode("\n", $output->all());

        $this->assertRegExp("~^.+failed.+{$failed_job_id}.+pushed\sback.+$~im", $output[0], $output_single);

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out'], $output_single);

        $this->assertSame($will_throws->getTries() * 2, Sharer::get(QueueJobThatThrowsException::class . '-throws'));
        $this->assertSame(2, Sharer::get(QueueJobThatThrowsException::class . '-failed'));
    }
}
