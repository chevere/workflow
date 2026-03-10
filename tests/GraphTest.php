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

use Chevere\Tests\src\TestActionNoParams;
use Chevere\Workflow\Graph;
use Chevere\Workflow\Interfaces\JobInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\workflow;

final class GraphTest extends TestCase
{
    public function testEmpty(): void
    {
        $graph = new Graph();
        $this->expectException(OutOfBoundsException::class);
        $graph->hasDependencies('j0');
    }

    public static function dataProviderWithPut(): array
    {
        return [['j1'], ['j2']];
    }

    #[DataProvider('dataProviderWithPut')]
    public function testWithPut(string $job): void
    {
        $graph = new Graph();
        $this->assertSame([], $graph->toArray());
        $with = $graph->withPut('j0', $this->getJob()->withDepends('j1'));
        $this->assertNotSame($graph, $with);
        $expected = [
            ['j1'],
            ['j0'],
        ];
        $this->assertSame($expected, $with->toArray());
        $with = $with->withPut('j0', $this->getJob()->withDepends('j2'));
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j0'],
            ],
            $with->toArray()
        );
        $with = $with->withPut('j1', $this->getJob());
        $with = $with->withPut('j2', $this->getJob());
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j0'],
            ],
            $with->toArray()
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Cannot declare job **{$job}** as a self-dependency
            PLAIN
        );
        $with->withPut(
            $job,
            async(TestActionNoParams::class)->withDepends('j0')
        );
    }

    public function testWithPutSync(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut(
            'jn',
            $this->getJob()->withDepends('j0', 'j1')
        );
        $graph = $graph->withPut(
            'jx',
            $this->getJob()->withDepends('j0', 'j1')
        );
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(true));
        $graph = $graph->withPut('j1', $this->getJob()->withIsSync(true));
        $this->assertSame(
            [
                ['j0'],
                ['j1'],
                ['jn', 'jx'],
            ],
            $graph->toArray()
        );
    }

    public function testWithPutSyncComplex(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut(
            'jn',
            $this->getJob()->withDepends('j0', 'j1')
        );
        $graph = $graph->withPut(
            'jx',
            $this->getJob()->withDepends('j2', 'j3')
        );
        $graph = $graph->withPut(
            'jy',
            $this->getJob()->withDepends('j2', 'j3', 'j4')
        );
        $graph = $graph->withPut('j2', $this->getJob()->withIsSync(true));
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(true));
        $graph = $graph->withPut('jy', $this->getJob()->withIsSync(true));
        $this->assertSame(
            [
                ['j0'],
                ['j2'],
                ['j1', 'j3', 'j4'],
                ['jy'],
                ['jn', 'jx'],
            ],
            $graph->toArray()
        );
    }

    public function testSyncToggleRemovesSyncFlag(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(true));
        $graph = $graph->withPut('j1', $this->getJob());
        $this->assertSame(
            [
                ['j0'],
                ['j1'],
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(false));
        $this->assertSame(
            [
                ['j0', 'j1'],
            ],
            $graph->toArray()
        );
    }

    public function testWithPutSyncDoesNotDuplicate(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(true));
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync(true));
        $this->assertSame(
            [
                ['j0'],
            ],
            $graph->toArray()
        );
    }

    public function testWithPutWea(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut('j0', $this->getJob());
        $this->assertTrue($graph->hasDependencies('j0'));
        $this->assertFalse($graph->hasDependencies('j0', 'jn'));
        $this->assertSame([], $graph->get('j0')->toArray());
        $graph = $graph->withPut('j1', $this->getJob());
        $graph = $graph->withPut('j2', $this->getJob()->withDepends('j0'));
        $this->assertTrue($graph->hasDependencies('j2', 'j0'));
        $this->assertSame(['j0'], $graph->get('j2')->toArray());
        $expected = [
            ['j0', 'j1'],
            ['j2'],
        ];
        $this->assertSame($expected, $graph->toArray());
    }

    public function testWithPutSelf(): void
    {
        $graph = new Graph();
        $this->expectException(InvalidArgumentException::class);
        $graph->withPut('j0', $this->getJob()->withDepends('j0'));
    }

    public function testWithPutDupes(): void
    {
        $graph = new Graph();
        $this->expectException(OverflowException::class);
        $graph->withPut('j0', $this->getJob()->withDepends('j1', 'j1'));
    }

    public function testWithPutEmpty(): void
    {
        $graph = new Graph();
        $this->expectException(InvalidArgumentException::class);
        $graph->withPut('job', $this->getJob()->withDepends(''));
    }

    public function testWithPutSpace(): void
    {
        $graph = new Graph();
        $this->expectException(InvalidArgumentException::class);
        $graph->withPut('job', $this->getJob()->withDepends(' '));
    }

    public function testWithPutDigit(): void
    {
        $graph = new Graph();
        $this->expectException(InvalidArgumentException::class);
        $graph->withPut('job', $this->getJob()->withDepends('123'));
    }

    public function testDependencyBatchesRespectProducerFirst(): void
    {
        $workflow = workflow(
            producer: sync(
                function (): int {
                    return 1;
                }
            ),
            dependent1: sync(
                function (int $producer): int {
                    return $producer + 1;
                },
                producer: response('producer')
            ),
            dependent2: sync(
                function (int $dependent1): int {
                    return $dependent1 + 1;
                },
                dependent1: response('dependent1')
            ),
        );
        $batches = $workflow->jobs()->graph()->toArray();
        $this->assertSame('producer', $batches[0][0]);
        $this->assertContains('dependent1', $batches[1]);
        $this->assertContains('dependent2', $batches[2]);
    }

    public function testGraphMultipleDependencies(): void
    {
        $workflow = workflow(
            subCreate: async(
                function (): int {
                    return 1;
                }
            ),
            orderCreate: async(
                function (): int {
                    return 2;
                }
            ),
            subOrderCreate: async(
                function (int $subCreate, int $orderCreate): int {
                    return $subCreate + $orderCreate;
                },
                subCreate: response('subCreate'),
                orderCreate: response('orderCreate')
            ),
        );
        $batches = $workflow->jobs()->graph()->toArray();
        $this->assertCount(2, $batches[0]);
        $this->assertContains('subCreate', $batches[0]);
        $this->assertContains('orderCreate', $batches[0]);
        $this->assertCount(1, $batches[1]);
        $this->assertSame('subOrderCreate', $batches[1][0]);
    }

    private function getJob(): JobInterface
    {
        return async(
            new TestActionNoParams()
        );
    }
}
