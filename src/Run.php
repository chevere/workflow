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

use Chevere\Container\Container;
use Chevere\Container\Interfaces\ContainerInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\DataStructure\Vector;
use Chevere\Message\Interfaces\MessageInterface;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\TypedInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use OverflowException;
use function Chevere\Message\message;
use function Chevere\Parameter\typed;

final class Run implements RunInterface
{
    /**
     * @template-use MapTrait<mixed>
     */
    use MapTrait;

    private string $uuid;

    private ArgumentsInterface $arguments;

    /**
     * Skipped jobs.
     *
     * @var VectorInterface<string>
     */
    private VectorInterface $skip;

    /**
     * @param mixed ...$variable Variables matching workflow parameters
     */
    public function __construct(
        private WorkflowInterface $workflow,
        private ContainerInterface $container = new Container(),
        mixed ...$variable
    ) {
        $this->uuid = uuidv4();
        $this->arguments = new Arguments(
            $workflow->parameters(),
            $variable
        );
        $this->map = new Map();
        $this->skip = new Vector();
        $this->container = $this->container
            ->withAutoInject($this->workflow()->dependencies());
        $this->workflow()->dependencies()->assert($this->container);
    }

    public function toArray(): array
    {
        $return = [];
        foreach ($this->map as $name => $mixed) {
            /** @var string $name */
            $return[$name] = $mixed;
        }

        return $return;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function workflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function arguments(): ArgumentsInterface
    {
        return $this->arguments;
    }

    public function skip(): VectorInterface
    {
        return $this->skip;
    }

    public function withResponse(string $job, mixed $response): RunInterface
    {
        $this->assertNoSkipOverflow($job, message('Job %job% is skipped'));
        $new = clone $this;
        $new->workflow->jobs()->get($job)->return()->__invoke($response);
        $new->map = $new->map->withPut($job, $response);

        return $new;
    }

    public function withSkip(string ...$job): RunInterface
    {
        $new = clone $this;
        foreach ($job as $item) {
            $new->workflow->jobs()->get($item);
            $new->assertNoSkipOverflow($item, message('Job %job% already skipped'));
            $new->skip = $new->skip->withPush($item);
        }

        return $new;
    }

    public function response(string $job, string|int ...$key): TypedInterface
    {
        return typed($this->map->get($job), ...$key);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReturn(string $job): TypedInterface
    {
        return $this->response($job);
    }

    private function assertNoSkipOverflow(string $job, MessageInterface $message): void
    {
        if ($this->skip->contains($job)) {
            throw new OverflowException(
                strtr($message->__toString(), [
                    '%job%' => $job,
                ])
            );
        }
    }
}
