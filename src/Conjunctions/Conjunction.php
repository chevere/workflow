<?php

namespace Chevere\Workflow\Conjunctions;

use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Vector;
use Chevere\Workflow\Interfaces\ConjunctionInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Closure;
use Iterator;
use OverflowException;
use function Chevere\Message\message;

abstract class Conjunction implements ConjunctionInterface
{
    protected VectorInterface $_conditions;

    abstract public function conjunction(): string;

    public function __construct(
        VariableInterface|ResponseReferenceInterface|ConjunctionInterface|callable ...$conditions
    )
    {
        $this->putConditions(...$conditions);
    }

    public function conditions(): VectorInterface
    {
        return $this->_conditions;
    }

    public function toArray(): array
    {
        return array_values(
            $this->conditions()->toArray()
        );
    }

    public function getIterator(): Iterator
    {
        return $this
            ->conditions()
            ->getIterator();
    }

    public function withPut(
        VariableInterface|ResponseReferenceInterface|ConjunctionInterface|callable ...$conditions
    ): static
    {
        return (clone $this)
            ->putConditions(...$conditions);
    }

    /**
     * @return array<int,string>
     */
    public function keys(): array
    {
        return array_values(
            $this->conditions()->keys()
        );
    }

    protected function putConditions(
        VariableInterface|ResponseReferenceInterface|ConjunctionInterface|callable ...$conditions
    ): static
    {
        $known = new Vector();
        $this->_conditions = new Vector();

        foreach ($conditions as $condition) {
            $id = match (true) {
                $condition instanceof ResponseReferenceInterface,
                $condition instanceof VariableInterface => $condition->__toString(),
                $condition instanceof ConjunctionInterface => 'conjunction#' . spl_object_id($condition),
                $condition instanceof Closure => 'callable#' . spl_object_id($condition),
                default => null,
            };

            if ( $id !== null && $known->contains($id) ) {
                throw new OverflowException(
                    (string) message(
                        'Condition `%condition%` is already defined',
                        condition: $id
                    )
                );
            }

            if ($id !== null) {
                $known = $known
                    ->withPush($id);

                $this->_conditions = $this
                    ->conditions()
                    ->withPush($condition);
            }
        }

        return $this;
    }
}