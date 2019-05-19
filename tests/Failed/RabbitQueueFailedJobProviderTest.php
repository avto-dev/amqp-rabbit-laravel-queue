<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Failed;

use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider<extended>
 */
class RabbitQueueFailedJobProviderTest extends AbstractTestCase
{
    /**
     * @small
     *
     * @return void
     */
    public function testWIP(): void
    {
        self::markTestIncomplete();
    }
}
