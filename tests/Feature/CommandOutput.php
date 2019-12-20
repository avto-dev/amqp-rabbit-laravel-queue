<?php

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Feature;

use Exception;
use Illuminate\Support\Collection;
use Throwable;

class CommandOutput extends Collection
{
    /**
     * Return as a plain text.
     *
     * @return string
     */
    public function getAsPlaintText(): string
    {
        $result = '';

        foreach ($this->items as $item) {
            $as_a_string = '';

            try {
                $as_a_string = (string) $item;
            } catch (Exception | Throwable $e) {
                // Do nothing
            }

            if (! empty($as_a_string) && \is_string($as_a_string)) {
                $result .= $as_a_string;
            }
        }

        return \trim($result);
    }
}
