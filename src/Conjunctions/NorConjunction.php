<?php

namespace Chevere\Workflow\Conjunctions;

use Chevere\Workflow\Interfaces\ConjunctionInterface;

class NorConjunction extends Conjunction
{
    public function conjunction(): string
    {
        return static::CONJUNCTION_NOR;
    }
}