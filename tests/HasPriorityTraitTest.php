<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests;

use AvtoDev\AmqpRabbitLaravelQueue\HasPriorityTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\HasPriorityTrait
 */
class HasPriorityTraitTest extends AbstractTestCase
{
    /**
     * @small
     *
     * @return void
     */
    public function testOnObjectWithProperty(): void
    {
        $object = new class {
            use HasPriorityTrait;

            protected $priority = 10;
        };

        $this->assertSame(10, $object->priority());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOnObjectWithoutProperty(): void
    {
        $object = new class {
            use HasPriorityTrait;
        };

        $this->assertSame(0, $object->priority());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOnObjectWithTooLargePropertyValue(): void
    {
        $object = new class {
            use HasPriorityTrait;

            protected $priority = 999;
        };

        $this->assertSame(255, $object->priority());
    }

    /**
     * @small
     *
     * @return void
     */
    public function testOnObjectWithTooLowPropertyValue(): void
    {
        $object = new class {
            use HasPriorityTrait;

            protected $priority = -999;
        };

        $this->assertSame(0, $object->priority());
    }
}
