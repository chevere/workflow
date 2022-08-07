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

use function Chevere\Message\message;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use function Chevere\VariableSupport\deepCopy;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Ds\Map;
use Ramsey\Uuid\Uuid;

final class Run implements RunInterface
{
    /**
     * @var Map<string, ResponseInterface>
     */
    private Map $jobs;

    private string $uuid;

    private ArgumentsInterface $arguments;

    public function __construct(private WorkflowInterface $workflow, mixed ...$variables)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->arguments = new Arguments($workflow->parameters(), ...$variables);
        $this->jobs = new Map();
    }

    public function __clone()
    {
        // @phpstan-ignore-next-line
        $this->jobs = deepCopy($this->jobs);
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

    public function withJobResponse(string $job, ResponseInterface $response): RunInterface
    {
        $new = clone $this;
        $new->workflow->jobs()->get($job);
        $tryArguments = new Arguments(
            $new->workflow->getJobReturnArguments($job),
            ...$response->data()
        );
        $tryArguments->parameters();
        $new->jobs->put($job, $response);

        return $new;
    }

    public function has(string $name): bool
    {
        return $this->jobs->hasKey($name);
    }

    /**
     * @throws TypeError
     * @throws OutOfBoundsException
     */
    public function get(string $name): ResponseInterface
    {
        try {
            return $this->jobs->get($name);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        // @phpstan-ignore-next-line
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                message('Job %name% not found')
                    ->withCode('%name%', $name)
            );
        }
    }
}
