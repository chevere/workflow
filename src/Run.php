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

use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\DataStructure\Vector;
use Chevere\Message\Interfaces\MessageInterface;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\CastInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use OverflowException;
use Ramsey\Uuid\Uuid;
use function Chevere\Message\message;
use function Chevere\VarSupport\deepCopy;

final class Run implements RunInterface
{
    /**
     * @template-use MapTrait<CastInterface>
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
        mixed ...$variable
    ) {
        $this->uuid = Uuid::uuid4()->toString();
        $this->arguments = new Arguments(
            $workflow->parameters(),
            $variable
        );
        $this->map = new Map();
        $this->skip = new Vector();
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

    public function skip(): VectorInterface
    {
        return $this->skip;
    }

    public function withResponse(string $job, CastInterface $response): RunInterface
    {
        $this->assertNoSkipOverflow($job, message('Job %job% is skipped'));
        $new = clone $this;
        $new->workflow->jobs()->get($job);
        $new->workflow->getJobResponseParameter($job)($response->mixed());
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

    public function getReturn(string $job): CastInterface
    {
        return $this->map->get($job);
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
