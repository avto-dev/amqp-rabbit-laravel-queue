<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Listeners;

use Illuminate\Support\Str;
use AvtoDev\AmqpRabbitLaravelQueue\Connector;
use AvtoDev\AmqpRabbitManager\Commands\Events\ExchangeDeleting;
use AvtoDev\AmqpRabbitLaravelQueue\Listeners\RemoveExchangeBind;
use AvtoDev\AmqpRabbitLaravelQueue\Tests\Traits\WithTemporaryRabbitConnectionTrait;

/**
 * @covers \AvtoDev\AmqpRabbitLaravelQueue\Listeners\RemoveExchangeBind<extended>
 */
class RemoveExchangeBindTest extends AbstractExchangeListenerTestCase
{
    use WithTemporaryRabbitConnectionTrait;

    /**
     * @var RemoveExchangeBind
     */
    protected $listener;

    /**
     * @var string
     */
    protected $listener_class = RemoveExchangeBind::class;

    /**
     * @small
     *
     * @return void
     */
    public function testHandle(): void
    {
        $this->doesntExpectEvents('queue.delayed-jobs.exchange.unbind');

        $event = new ExchangeDeleting($this->temp_rabbit_connection, $this->temp_rabbit_exchange, Str::random());

        $this->listener->handle($event);
    }

    /**
     * @small
     *
     * @return void
     */
    public function testHandleFired(): void
    {
        $this->expectsEvents('queue.delayed-jobs.exchange.unbind');

        $this->config()->set('queue.connections', [
            'foo' => [
                'driver'              => Connector::NAME,
                'queue_id'            => $this->temp_rabbit_queue_id,
                'delayed_exchange_id' => $this->temp_rabbit_exchange_id,
            ],
        ]);

        $this->listener = $this->app->make($this->listener_class);

        $event = new ExchangeDeleting(
            $this->temp_rabbit_connection,
            $this->temp_rabbit_exchange,
            $this->temp_rabbit_exchange_id
        );

        $this->listener->handle($event);
    }
}
