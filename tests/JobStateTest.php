<?php

declare(strict_types = 1);

namespace Tests\Unit\AvtoDev\AmqpRabbitLaravelQueue\Tests;

use Serializable;
use InvalidArgumentException;
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
    protected $instance;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->instance = new JobState;
    }

    /**
     * @small
     *
     * @return void
     */
    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(JobStateInterface::class, $this->instance);
        $this->assertInstanceOf(Serializable::class, $this->instance);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testExistedMethods(): void
    {
        foreach (['serialize', 'unserialize', 'put', 'all', 'has', 'get', 'put', 'isEmpty', 'forget'] as $method) {
            $this->assertTrue(\method_exists($this->instance, $method));
        }
    }

    /**
     * @small
     *
     * @return void
     */
    public function testSerializeMethod(): void
    {
        $this->instance->put('some_items', $items = ['foo', 'bar', 23]);

        $this->assertSame(\serialize(['some_items' => $items]), $this->instance->serialize());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testUnserializeMethod(): void
    {
        $data = \serialize($items = ['some_items' => ['foo', 'bar', 23]]);
        $this->instance->unserialize($data);

        $this->assertSame($items, $this->instance->all());
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

    /**
     * @small
     *
     * @return void
     */
    public function testPutMethod(): void
    {
        foreach (['item_name', 'foo', 'bar', 23] as $item) {
            $this->assertNull($this->instance->put($item, [$item]));
            $this->assertSame([$item], $this->instance->get($item));
        }
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPutThrowsAnExceptionWhenPassedCallable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('~Wrong value passed~i');

        $this->instance->put('foo', function (): void {
        });
    }

    /**
     * @small
     *
     * @return void
     */
    public function testPutThrowsAnExceptionWhenPassedResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('~Wrong value passed~i');

        $this->instance->put('foo', \tmpfile());
    }
}
