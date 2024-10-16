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

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use InvalidArgumentException;

/**
 * @template-implements Task<mixed, never, never>
 */
final class CallableTask implements Task
{
    private string $callable;

    /**
     * @var array<mixed>
     */
    private array $arguments;

    public function __construct(
        callable $callable,
        mixed ...$arguments
    ) {
        if (! is_string($callable)) {
            throw new InvalidArgumentException('Argument $callable must be a callable string');
        }
        $this->callable = $callable;
        $this->arguments = $arguments;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        // @phpstan-ignore-next-line
        return ($this->callable)(...$this->arguments);
    }
}
