<?php

namespace Tests\Unit\AvtoDev\AmqpRabbitLaravelQueue\Tests;

use AvtoDev\AmqpRabbitLaravelQueue\JobState;
use AvtoDev\AmqpRabbitLaravelQueue\JobStateInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;

class JobStateTest extends AbstractTestCase
{
    /**
     * @small
     */
    public function testExistedMethods(): void
    {
        /** @var JobState $job_state_class */
        $job_state_class = $this->app->make(JobState::class);

        self::assertInstanceOf(JobStateInterface::class, $job_state_class);

        foreach (['serialize', 'unserialize', 'put', 'all', 'has', 'get', 'put', 'isEmpty', 'forget',] as $method) {
            $this->assertTrue(\method_exists($job_state_class, $method));
        }
    }
}
