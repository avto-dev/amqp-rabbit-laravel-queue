<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer\Sharer;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\SimpleQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobWithDelay;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\PrioritizedQueueJob;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Stubs\QueueJobThatThrowsException;

/**
 * @group feature
 */
class QueueWorkerTest extends AbstractFeatureTest
{
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
        /** @var CommandOutput $output */
        $output         = $process_info['stdout'];
        $job_class_safe = \preg_quote(SimpleQueueJob::class, '/');

        $this->assertEmpty($process_info['stderr']);
        $this->assertRegExp('~^\d{2}\:\d{2}\:\d{2}\.\d{3}.+start.+$~im', $output[0] ?? '', $output->getAsPlaintText());
        $this->assertRegExp("~^.+processing.+{$job_class_safe}.?$~im", $output[1] ?? '', $output->getAsPlaintText());
        $this->assertRegExp("~^.+processed.+{$job_class_safe}.?$~im", $output[2] ?? '', $output->getAsPlaintText());
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
        /** @var CommandOutput $output */
        $output = $process_info['stdout'];

        $this->assertGreaterThanOrEqual(5, $output->count(), $output->getAsPlaintText());
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
        /** @var CommandOutput $output */
        $output = $process_info['stdout'];

        // Should be processed FIRST
        $this->assertRegExp(
            '~' . \preg_quote(PrioritizedQueueJob::class, '/') . '$~i',
            $output[1] ?? '',
            $output->getAsPlaintText()
        );

        $this->assertGreaterThanOrEqual(9, $output->count(), $output->getAsPlaintText());
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
        /** @var CommandOutput $output */
        $output = $process_info['stdout'];

        $this->assertGreaterThanOrEqual(6, $output->count(), $output->getAsPlaintText());
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
        /** @var CommandOutput $output */
        $output = $process_info['stdout'];

        $this->assertRegExp(
            "~^.+failed.+{$failed_job_id}.+pushed\sback.+$~im",
            $output[0] ?? '',
            $output->getAsPlaintText()
        );

        $process_info = $this->startArtisan('queue:work');

        $this->assertTrue($process_info['timed_out'], $output->getAsPlaintText());

        $this->assertSame($will_throws->getTries() * 2, Sharer::get(QueueJobThatThrowsException::class . '-throws'));
        $this->assertSame(2, Sharer::get(QueueJobThatThrowsException::class . '-failed'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testOnceParameter(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch(new SimpleQueueJob);

        $process_info = $this->startArtisan('queue:work', ['--once']);

        $this->assertFalse($process_info['timed_out']);
        /** @var CommandOutput $output */
        $output = $process_info['stdout'];

        $this->assertGreaterThanOrEqual(3, $output->count(), $output->getAsPlaintText());
        $this->assertSame(1, Sharer::get(SimpleQueueJob::class . '-handled'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testDelayedJob(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch((new SimpleQueueJob)->delay(999999)); // Should be grater then timeout

        $process_info = $this->startArtisan('queue:work');
        $this->assertTrue($process_info['timed_out']);

        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testDelayedJobWithRegular(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch((new SimpleQueueJob)->delay(999999)); // Should be grater then timeout
        $this->dispatcher->dispatch(new SimpleQueueJob);

        $process_info = $this->startArtisan('queue:work');
        $this->assertTrue($process_info['timed_out']);

        $this->assertSame(2, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testDelayedJobProcessing(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch((new SimpleQueueJob)->delay($delay = 2)); // Should be LESS then timeout

        $process_info = $this->startArtisan('queue:work', [], 3.0);
        $this->assertTrue($process_info['timed_out']);

        $this->assertSame(1, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));

        $when = Sharer::get(SimpleQueueJob::class . '-when');

        $this->assertEquals($this->now + $delay, $when, 'Jobs processed with wrong delay', 1);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testFailingJobWithRetryDelay(): void
    {
        $this->assertFalse(Sharer::has(QueueJobWithDelay::class . '-handled'));

        $this->dispatcher->dispatch($job = new QueueJobWithDelay);

        $process_info = $this->startArtisan('queue:work', [], 3.0);
        $this->assertTrue($process_info['timed_out']);

        $this->assertSame(1, Sharer::get(QueueJobWithDelay::class . '-handled'));
        $this->assertFalse(Sharer::has(QueueJobWithDelay::class . '-failed'));

        $when = Sharer::get(QueueJobWithDelay::class . '-when');

        $this->assertEquals($this->now + $job->delay, $when, 'Jobs processed with wrong delay', 1);
    }

    /**
     * @medium
     *
     * @return void
     */
    public function testWithWorkerTimeout(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch($job = new SimpleQueueJob);
        $this->dispatcher->dispatch((new SimpleQueueJob)->delay(3)); // Should be grater then timeout

        $process_info = $this->startArtisan('queue:work', [], 2.0, ['QUEUE_TIMEOUT' => 1500]);
        $this->assertFalse($process_info['timed_out']);

        \usleep(1500000); // 1.5 sec

        $this->assertSame(1, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));

        $when = Sharer::get(SimpleQueueJob::class . '-when');

        $this->assertEquals($this->now, $when, 'Jobs processed with wrong delay', 1);
    }

    /**
     * @medium
     *
     * @return void
     *
     * @group foo
     */
    public function testWithWorkerTimeoutAndResume(): void
    {
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-handled'));

        $this->dispatcher->dispatch(new SimpleQueueJob);
        $this->dispatcher->dispatch((new SimpleQueueJob)->delay(1)); // Should be grater then queue timeout

        $process_info = $this->startArtisan('queue:work', [], 2.0, [
            'QUEUE_TIMEOUT' => 800,
            'QUEUE_RESUME'  => true,
        ]);
        $this->assertTrue($process_info['timed_out']);

        $this->assertSame(2, Sharer::get(SimpleQueueJob::class . '-handled'));
        $this->assertFalse(Sharer::has(SimpleQueueJob::class . '-failed'));
    }
}
