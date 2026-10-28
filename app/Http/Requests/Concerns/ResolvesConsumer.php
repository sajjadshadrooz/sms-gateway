<?php

namespace App\Http\Requests\Concerns;

use App\Models\Consumer;

trait ResolvesConsumer
{
    private ?Consumer $resolvedConsumer = null;

    private bool $consumerResolved = false;

    public function consumer(): ?Consumer
    {
        if (! $this->consumerResolved) {
            $this->resolvedConsumer = Consumer::find($this->route('consumerId'));
            $this->consumerResolved = true;
        }

        return $this->resolvedConsumer;
    }
}
