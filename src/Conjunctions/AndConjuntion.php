<?php

namespace Chevere\Workflow\Conjunctions;

use Chevere\Workflow\Interfaces\ConjunctionInterface;

class AndConjuntion extends Conjunction
{
    public function conjunction(): string
    {
        return static::CONJUNCTION_AND;
    }
}