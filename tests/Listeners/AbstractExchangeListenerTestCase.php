<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Listeners;

use AvtoDev\AmqpRabbitLaravelQueue\Tests\AbstractTestCase;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\AbstractExchangeBindListener;

abstract class AbstractExchangeListenerTestCase extends AbstractTestCase
{
    /**
     * @var AbstractExchangeBindListener
     */
    protected $listener;

    /**
     * @var string
     */
    protected $listener_class;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = $this->app->make($this->listener_class);
    }
}
