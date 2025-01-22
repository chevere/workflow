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

namespace Chevere\Workflow\Traits;

use Chevere\Workflow\Exceptions\WorkflowException;
use Closure;

/**
 * Shorthand for testing a job runtime WorkflowException for PHPUnit.
 */
trait ExpectWorkflowExceptionTrait
{
    abstract public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void;

    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    /**
     * Expect a WorkflowException.
     *
     * @param Closure $closure The logic to test.
     * @param string $job The expected job that will throw exception.
     * @param string $instance The expected job exception instance.
     * @param int $code The expected job exception code.
     */
    private function expectWorkflowException(
        Closure $closure,
        string $instance,
        ?string $job,
        ?string $message,
        ?int $code
    ): void {
        try {
            $closure();
        } catch (WorkflowException $e) {
            $this->assertInstanceOf($instance, $e->throwable);
            if ($job !== null) {
                $this->assertSame($job, $e->name);
            }
            if ($message !== null) {
                $this->assertSame($message, $e->throwable->getMessage());
            }
            if ($code !== null) {
                $this->assertSame($code, $e->throwable->getCode());
            }
        }
    }
}
