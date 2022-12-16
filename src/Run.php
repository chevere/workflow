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

use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\OutOfRangeException;
use function Chevere\VariableSupport\deepCopy;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Ramsey\Uuid\Uuid;

final class Run implements RunInterface
{
    use MapTrait;

    /**
     * @var MapInterface<string, ResponseInterface>
     */
    private MapInterface $map;

    private string $uuid;

    private ArgumentsInterface $arguments;

    public function __construct(
        private WorkflowInterface $workflow,
        mixed ...$variables
    ) {
        $this->uuid = Uuid::uuid4()->toString();
        $this->arguments = new Arguments(
            $workflow->parameters(),
            ...$variables
        );
        $this->map = new Map();
    }

    public function __clone()
    {
        // @phpstan-ignore-next-line
        $this->map = deepCopy($this->map);
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
            $new->workflow->getJobResponseParameters($job),
            ...$response->data()
        );
        $tryArguments->parameters();
        $new->map = $new->map->withPut(...[
            $job => $response,
        ]);

        return $new;
    }

    public function has(string ...$name): bool
    {
        return $this->map->has(...$name);
    }

    /**
     * @throws \TypeError
     * @throws OutOfRangeException
     */
    public function get(string $name): ResponseInterface
    {
        /** @var ResponseInterface */
        return $this->map->get($name);
    }
}
