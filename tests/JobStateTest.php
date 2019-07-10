<?php

declare(strict_types = 1);

namespace Tests\Unit\AvtoDev\AmqpRabbitLaravelQueue\Tests;

use AvtoDev\AmqpRabbitLaravelQueue\JobState;
use AvtoDev\AmqpRabbitLaravelQueue\JobStateInterface;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\JobState<extended>
 */
class JobStateTest extends AbstractTestCase
{
    /**
     * @var JobState
     */
    protected $job_state_class;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->job_state_class = $this->app->make(JobState::class);
        $this->assertInstanceOf(JobStateInterface::class, $this->job_state_class);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testExistedMethods(): void
    {
        foreach (['serialize', 'unserialize', 'put', 'all', 'has', 'get', 'put', 'isEmpty', 'forget'] as $method) {
            $this->assertTrue(\method_exists($this->job_state_class, $method));
        }
    }

    /**
     * @small
     *
     * @return void
     */
    public function testSerializeMethod(): void
    {
        $this->job_state_class->put('some_items', $items = ['foo', 'bar', 23]);

        $this->assertSame(\serialize(['some_items' => $items]), $this->job_state_class->serialize());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testUnserializeMethod(): void
    {
        $data = \serialize($items = ['some_items' => ['foo', 'bar', 23]]);
        $this->job_state_class->unserialize($data);

        $this->assertSame($items, $this->job_state_class->all());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPutMethod(): void
    {
        foreach (['item_name', 'foo', 'bar', 23] as $item) {
            $return_value = $this->job_state_class->put($item, [$item]);

            $this->assertNull($return_value);
            $this->assertSame([$item], $this->job_state_class->get($item));
        }
    }

    /**
     * @small
     *
     * @return void
     */
    public function testGetInstanceFromUnserialize(): void
    {
        $data = [123, 'foo' => 'bar', false, null, \M_PI];

        $this->assertSame($data, \unserialize(\serialize(new JobState($data)))->all());
    }
}
