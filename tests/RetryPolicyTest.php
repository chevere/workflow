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

namespace Chevere\Tests;

use Chevere\Workflow\RetryPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testConstructDefault(): void
    {
        $retryPolicy = new RetryPolicy();
        $this->assertSame(0, $retryPolicy->timeout());
        $this->assertSame(1, $retryPolicy->maxAttempts());
        $this->assertSame(0, $retryPolicy->delay());
    }

    #[DataProvider('dataProviderConstructFail')]
    public function testConstructFail(string $property, int $invalidValue): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryPolicy(...[
            $property => $invalidValue,
        ]);
    }

    public static function dataProviderConstructFail(): array
    {
        return [
            ['timeout', -1],
            ['maxAttempts', 0],
            ['delay', -1],
        ];
    }
}
