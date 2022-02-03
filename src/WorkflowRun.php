<?php

/*
 * This file is part of Chevere.
 *
 * (c) Rodolfo Berrios <rodolfo@chevere.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Chevere\Workflow;

use Chevere\Message\Message;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use function Chevere\VarSupport\deepCopy;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Ds\Map;
use Ramsey\Uuid\Uuid;

final class WorkflowRun implements WorkflowRunInterface
{
    private Map $steps;

    private string $uuid;

    private ArgumentsInterface $arguments;

    public function __construct(private WorkflowInterface $workflow, mixed ...$namedArguments)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->arguments = new Arguments($workflow->parameters(), ...$namedArguments);
        $this->steps = new Map();
    }

    public function __clone()
    {
        $this->steps = deepCopy($this->steps);
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function workflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function arguments(): ArgumentsInterface
    {
        return $this->arguments;
    }

    public function withStepResponse(string $step, ResponseInterface $response): WorkflowRunInterface
    {
        $new = clone $this;
        $new->workflow->steps()->get($step);
        $tryArguments = new Arguments(
            $new->workflow->getProvided($step),
            ...$response->data()
        );
        $tryArguments->parameters();
        $new->steps->put($step, $response);

        return $new;
    }

    public function has(string $name): bool
    {
        return $this->steps->hasKey($name);
    }

    /**
     * @throws TypeError
     * @throws OutOfBoundsException
     */
    public function get(string $name): ResponseInterface
    {
        try {
            return $this->steps->get($name);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                (new Message('Step %name% not found'))
                    ->code('%name%', $name)
            );
        }
    }
}
