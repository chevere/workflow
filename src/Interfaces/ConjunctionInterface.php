<?php

namespace Chevere\Workflow\Interfaces;

use Chevere\DataStructure\Interfaces\VectorInterface;
use Iterator;

interface ConjunctionInterface
{
    const string CONJUNCTION_AND = 'and';
    const string CONJUNCTION_OR = 'or';
    const string CONJUNCTION_NOR = 'nor';

    public function conjunction(): string;

    public function conditions(): VectorInterface;

    public function keys(): array;

    public function toArray(): array;

    public function getIterator(): Iterator;

    public function withPut(VariableInterface|ResponseReferenceInterface|ConjunctionInterface|callable ...$conditions): static;
}