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

namespace Chevere\Workflow\Interfaces;

use Chevere\Throwable\Exceptions\InvalidArgumentException;

/**
 * Describes the component in charge of defining a job.
 */
interface JobInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $action,
        mixed ...$namedArguments
    );

    public function withArguments(mixed ...$namedArguments): self;

    public function withRunIf(ReferenceInterface|VariableInterface ...$context): JobInterface;

    public function withIsSync(): self;

    public function withDepends(string ...$jobs): self;

    public function action(): string;

    /**
     * @return Array<string, mixed>
     */
    public function arguments(): array;

    /**
     * @return string[]
     */
    public function dependencies(): array;

    public function isSync(): bool;

    /**
     * @return array<ReferenceInterface|VariableInterface>
     */
    public function runIf(): array;
}
