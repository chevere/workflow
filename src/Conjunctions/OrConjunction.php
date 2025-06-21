<?php

namespace Chevere\Workflow\Conjunctions;

use Chevere\Workflow\Interfaces\ConjunctionInterface;

class OrConjunction extends Conjunction
{
    public function conjunction(): string
    {
        return static::CONJUNCTION_OR;
    }
}