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

use Chevere\Workflow\Graph;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\sync;

final class GraphEdgeCasesTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 1: Simple chain with forward references
    //
    //   A → B → C → D → E  (all async)
    //
    // Jobs are added in the order A, B, C, D, E.
    // When A is added, B doesn't exist yet; when B is added, C doesn't
    // exist yet, etc. So transitive deps are never backfilled.
    //
    // Expected batches: [[E], [D], [C], [B], [A]]
    // ──────────────────────────────────────────────────────────────
    public function testLongChainForwardOrder(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('A', $async->withDepends('B'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('C', $async->withDepends('D'))
            ->withPut('D', $async->withDepends('E'))
            ->withPut('E', $async);
        $this->assertSame(
            ['B', 'C', 'D', 'E'],
            $graph->get('A')->toArray(),
            'A should have full transitive deps [B, C, D, E]'
        );
        $this->assertSame(
            ['C', 'D', 'E'],
            $graph->get('B')->toArray(),
            'B should have full transitive deps [C, D, E]'
        );
        $batches = $graph->toArray();
        $expected = [['E'], ['D'], ['C'], ['B'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'A long chain added in forward order should produce strictly sequential batches. '
            . 'Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 2: Diamond + chain (forward order)
    //
    //       A
    //      / \
    //     B   C
    //      \ /
    //       D
    //       |
    //       E
    //
    // Insertion order: A, B, C, D, E
    // Expected: [[E], [D], [B, C], [A]]
    // ──────────────────────────────────────────────────────────────
    public function testDiamondChainForwardOrder(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('A', $async->withDepends('B', 'C'))
            ->withPut('B', $async->withDepends('D'))
            ->withPut('C', $async->withDepends('D'))
            ->withPut('D', $async->withDepends('E'))
            ->withPut('E', $async);
        $batches = $graph->toArray();
        $expected = [['E'], ['D'], ['B', 'C'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'Diamond + chain should produce 4 levels. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 3: Two independent chains merged at the bottom
    //
    //   A → B → C → X  (chain 1)
    //   D → E → X      (chain 2)
    //
    // Insertion order: A, B, D, E, C, X
    // Expected: [[X], [C, E], [B, D], [A]]
    // (D and A are both at the top of their chains)
    //
    // Actually: A→B→C→X and D→E→X
    // Levels: X=0, C=1, E=1, B=2, D=2, A=3
    // Expected: [[X], [C, E], [B, D], [A]]
    // ──────────────────────────────────────────────────────────────
    public function testTwoChainsWithSharedRoot(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph->withPut('A', $async->withDepends('B'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('D', $async->withDepends('E'))
            ->withPut('E', $async->withDepends('X'))
            ->withPut('C', $async->withDepends('X'))
            ->withPut('X', $async);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 'A', 'B');
        $this->assertBatchOrder($batches, 'B', 'C');
        $this->assertBatchOrder($batches, 'C', 'X');
        $this->assertBatchOrder($batches, 'D', 'E');
        $this->assertBatchOrder($batches, 'E', 'X');
        $expected = [['X'], ['C', 'E'], ['B', 'D'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'Two chains sharing a root should merge correctly. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 4: Linear chain where inner dep has a side branch
    //
    //   A → B → C → D → E     (main chain)
    //               |
    //               F          (side branch: C also depends on F)
    //
    // But F is independent of D-E chain.
    //
    // Insertion order: A, B, C, D, E, F
    //
    // When C is added, it depends on [D]. D is already in graph
    // (as placeholder from B's insertion), but D has no deps yet.
    // When D is added with dep E, C's deps are not updated.
    // When F is added, C needs to be updated to also depend on F,
    // but that requires re-adding C.
    //
    // Instead let's do: C depends on D and F from the start.
    // Insertion: A, B, C, D, E, F
    //
    // Expected: [[F, E], [D], [C], [B], [A]]
    // ──────────────────────────────────────────────────────────────
    public function testLinearChainWithSideBranch(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('A', $async->withDepends('B'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('C', $async->withDepends('D', 'F'))
            ->withPut('D', $async->withDepends('E'))
            ->withPut('E', $async)
            ->withPut('F', $async);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 'A', 'B');
        $this->assertBatchOrder($batches, 'B', 'C');
        $this->assertBatchOrder($batches, 'C', 'D');
        $this->assertBatchOrder($batches, 'C', 'F');
        $this->assertBatchOrder($batches, 'D', 'E');
        $expected = [['F', 'E'], ['D'], ['C'], ['B'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'Chain with side branch should sort correctly. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 5: Sync job at the end of two independent chains
    //
    //   j1 (async) → j3 (sync)
    //   j2 (async) → j3 (sync)
    //
    // Insertion order: j1, j2, j3
    // Expected: [[j1, j2], [j3]]
    // j3 is sync and depends on both j1 and j2
    // ──────────────────────────────────────────────────────────────
    public function testSyncJobDependingOnMultipleAsync(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut('j1', $async)
            ->withPut('j2', $async)
            ->withPut('j3', $sync->withDepends('j1', 'j2'));
        $batches = $graph->toArray();
        $expected = [['j1', 'j2'], ['j3']];
        $this->assertSame(
            $expected,
            $batches,
            'Sync job depending on async jobs. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 6: Mixed sync/async with deep forward-reference chain
    //
    //   s1 (sync) → s2 (sync) → a1 (async) → a2 (async)
    //
    // Insertion order: s1, s2, a1, a2
    // Expected: [[a2], [a1], [s2], [s1]]
    // Each sync job gets its own batch, async can parallelize.
    // ──────────────────────────────────────────────────────────────
    public function testSyncChainOverAsyncChainForwardOrder(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut('s1', $sync->withDepends('s2'))
            ->withPut('s2', $sync->withDepends('a1'))
            ->withPut('a1', $async->withDepends('a2'))
            ->withPut('a2', $async);
        $batches = $graph->toArray();
        // s1 must be after s2, s2 after a1, a1 after a2
        $this->assertBatchOrder($batches, 's1', 's2');
        $this->assertBatchOrder($batches, 's2', 'a1');
        $this->assertBatchOrder($batches, 'a1', 'a2');
        $expected = [['a2'], ['a1'], ['s2'], ['s1']];
        $this->assertSame(
            $expected,
            $batches,
            'Sync-over-async chain in forward order. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 7: Wide fan-in with all sync dependencies
    //
    //             collector (async)
    //            / | | | \
    //          s1  s2 s3 s4 s5  (all sync, no deps)
    //
    // Insertion order: collector, s1, s2, s3, s4, s5
    //
    // Expected: Each sync job is its own batch, then collector last.
    // All sync jobs are independent, but each forms its own batch.
    // ──────────────────────────────────────────────────────────────
    public function testWideFanInSyncToAsync(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut(
                'collector',
                $async->withDepends('s1', 's2', 's3', 's4', 's5')
            )
            ->withPut('s1', $sync)
            ->withPut('s2', $sync)
            ->withPut('s3', $sync)
            ->withPut('s4', $sync)
            ->withPut('s5', $sync);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 'collector', 's1');
        $this->assertBatchOrder($batches, 'collector', 's2');
        $this->assertBatchOrder($batches, 'collector', 's3');
        $this->assertBatchOrder($batches, 'collector', 's4');
        $this->assertBatchOrder($batches, 'collector', 's5');
        $lastBatch = end($batches);
        $this->assertContains(
            'collector',
            $lastBatch,
            'Collector should be in the last batch. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 8: Non-transitive comparison exposure
    //
    // This test specifically targets the uksort comparison function
    // in getSortAsc(). With incomplete transitive deps, the comparator
    // can violate transitivity: A > B and B > C but A == C.
    //
    //   A → B → C  (chain, each has 1 dep)
    //   X → Y      (independent chain, each has 1 dep)
    //   C → X      (cross-chain link)
    //
    // Full chain: A → B → C → X → Y
    //
    // Insertion order: A, X, B, C, Y
    //
    // After insertion:
    //   A → [B], B → [C], C → [X], X → [Y], Y → []
    //   (No transitive expansion because deps are forward refs)
    //
    // Expected: [[Y], [X], [C], [B], [A]]
    // ──────────────────────────────────────────────────────────────
    public function testNonTransitiveComparisonExposure(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('A', $async->withDepends('B'))
            ->withPut('X', $async->withDepends('Y'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('C', $async->withDepends('X'))
            ->withPut('Y', $async);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 'A', 'B');
        $this->assertBatchOrder($batches, 'B', 'C');
        $this->assertBatchOrder($batches, 'C', 'X');
        $this->assertBatchOrder($batches, 'X', 'Y');
        $expected = [['Y'], ['X'], ['C'], ['B'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'Cross-chain linear dependency must be fully sequential. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 9: Complex mixed sync/async with forward refs
    //
    //   s1 (sync) → a1 (async) → a3 (async)
    //   s2 (sync) → a2 (async) → a3 (async)
    //   a4 (async) → a3 (async)
    //
    // Insertion order: s1, s2, a4, a1, a2, a3
    //
    // Expected:
    //   Level 0: [a3]
    //   Level 1: [a1, a2, a4]  (all depend on a3)
    //   Level 2: [s1] (sync, depends on a1)
    //   Level 3: [s2] (sync, depends on a2)
    //   Or s1 and s2 could be in any relative order since they're independent.
    // ──────────────────────────────────────────────────────────────
    public function testMixedSyncAsyncWithForwardRefs(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut('s1', $sync->withDepends('a1'))
            ->withPut('s2', $sync->withDepends('a2'))
            ->withPut('a4', $async->withDepends('a3'))
            ->withPut('a1', $async->withDepends('a3'))
            ->withPut('a2', $async->withDepends('a3'))
            ->withPut('a3', $async);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 's1', 'a1');
        $this->assertBatchOrder($batches, 's2', 'a2');
        $this->assertBatchOrder($batches, 'a4', 'a3');
        $this->assertBatchOrder($batches, 'a1', 'a3');
        $this->assertBatchOrder($batches, 'a2', 'a3');
        $found = false;
        foreach ($batches as $batch) {
            if (in_array('a1', $batch) && in_array('a2', $batch) && in_array('a4', $batch)) {
                $found = true;

                break;
            }
        }
        $this->assertTrue(
            $found,
            'a1, a2, a4 should be in the same async batch. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 10: hasDependencies with transitive deps
    //
    // Tests whether hasDependencies correctly reports transitive
    // dependencies when they were not known at insertion time.
    //
    //   A → B → C  (all async)
    //   Insertion order: A, B, C
    //
    // A should transitively depend on C.
    // ──────────────────────────────────────────────────────────────
    public function testHasDependenciesTransitive(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('A', $async->withDepends('B'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('C', $async);
        $this->assertTrue(
            $graph->hasDependencies('A', 'B'),
            'A directly depends on B'
        );
        $this->assertTrue(
            $graph->hasDependencies('B', 'C'),
            'B directly depends on C'
        );
        $this->assertTrue(
            $graph->hasDependencies('A', 'C'),
            'A should transitively depend on C (A → B → C). '
            . 'Stored deps for A: ' . json_encode($graph->get('A')->toArray())
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 11: Long chain added in reverse order (should work)
    //
    //   A → B → C → D → E  (all async)
    //   Insertion order: E, D, C, B, A  (leaves first)
    //
    // This should work correctly because transitive closure CAN
    // be computed — all deps exist when the job is added.
    // ──────────────────────────────────────────────────────────────
    public function testLongChainReverseOrder(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('E', $async)
            ->withPut('D', $async->withDepends('E'))
            ->withPut('C', $async->withDepends('D'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('A', $async->withDepends('B'));
        $this->assertSame(
            ['B', 'C', 'D', 'E'],
            $graph->get('A')->toArray(),
            'When added in reverse order, A should have full transitive deps'
        );
        $batches = $graph->toArray();
        $expected = [['E'], ['D'], ['C'], ['B'], ['A']];
        $this->assertSame(
            $expected,
            $batches,
            'Reverse order chain should produce sequential batches. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 12: Insertion order affects result (ordering asymmetry)
    //
    // Same logical graph, different insertion orders, should produce
    // the same batches. This demonstrates insertion-order sensitivity.
    //
    //   A → C, B → C, C (no deps)
    //
    // Order 1: C, A, B  (leaves first)
    // Order 2: A, B, C  (roots first)
    // ──────────────────────────────────────────────────────────────
    public function testInsertionOrderShouldNotAffectResult(): void
    {
        $g1 = new Graph();
        $async = async(fn () => null);
        $g1 = $g1
            ->withPut('C', $async)
            ->withPut('A', $async->withDepends('C'))
            ->withPut('B', $async->withDepends('C'));
        $g2 = new Graph();
        $g2 = $g2
            ->withPut('A', $async->withDepends('C'))
            ->withPut('B', $async->withDepends('C'))
            ->withPut('C', $async);
        $b1 = $g1->toArray();
        $b2 = $g2->toArray();
        $b1n = array_map(
            function ($batch) {
                sort($batch);

                return $batch;
            },
            $b1
        );
        $b2n = array_map(
            function ($batch) {
                sort($batch);

                return $batch;
            },
            $b2
        );
        $this->assertSame(
            $b1n,
            $b2n,
            'Same graph should produce same batches regardless of insertion order. '
            . 'Order1: ' . json_encode($b1) . ' Order2: ' . json_encode($b2)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 13: Sync jobs that should be independent get serialized
    //
    //   s1 (sync, no deps)
    //   s2 (sync, no deps)
    //   s3 (sync, no deps)
    //
    // These are independent sync jobs. The graph separates each sync
    // job into its own batch. But they have no deps on each other,
    // so they COULD theoretically run in parallel. The current design
    // forces them sequential. This is a design choice, not a bug,
    // but worth documenting.
    // ──────────────────────────────────────────────────────────────
    public function testIndependentSyncJobsSerialized(): void
    {
        $graph = new Graph();
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut('s1', $sync)
            ->withPut('s2', $sync)
            ->withPut('s3', $sync);
        $batches = $graph->toArray();
        $this->assertCount(
            3,
            $batches,
            'Independent sync jobs should each get their own batch. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 14: Large fan-out then fan-in (stress test sort)
    //
    //          root
    //       /  |  |  \
    //     m1  m2  m3  m4   (all async, depend on root)
    //       \  |  |  /
    //        sink           (async, depends on m1-m4)
    //
    // Insertion order: sink, m1, m2, m3, m4, root
    // All forward references.
    // ──────────────────────────────────────────────────────────────
    public function testFanOutFanInForwardOrder(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $graph = $graph
            ->withPut('sink', $async->withDepends('m1', 'm2', 'm3', 'm4'))
            ->withPut('m1', $async->withDepends('root'))
            ->withPut('m2', $async->withDepends('root'))
            ->withPut('m3', $async->withDepends('root'))
            ->withPut('m4', $async->withDepends('root'))
            ->withPut('root', $async);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 'sink', 'm1');
        $this->assertBatchOrder($batches, 'sink', 'm2');
        $this->assertBatchOrder($batches, 'sink', 'm3');
        $this->assertBatchOrder($batches, 'sink', 'm4');
        $this->assertBatchOrder($batches, 'm1', 'root');
        $this->assertBatchOrder($batches, 'm2', 'root');
        $this->assertBatchOrder($batches, 'm3', 'root');
        $this->assertBatchOrder($batches, 'm4', 'root');
        $expected = [['root'], ['m1', 'm2', 'm3', 'm4'], ['sink']];
        $middleBatch = $batches[1] ?? [];
        sort($middleBatch);
        if (isset($batches[1])) {
            $batches[1] = $middleBatch;
        }
        $expectedMiddle = ['m1', 'm2', 'm3', 'm4'];
        sort($expectedMiddle);
        $expected[1] = $expectedMiddle;
        $this->assertSame(
            $expected,
            $batches,
            'Fan-out/fan-in should produce 3 levels. Got: ' . json_encode($batches)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // EDGE CASE 15: Sync job with transitive dep on another sync job
    //
    //   s1 (sync) → a1 (async) → s2 (sync)
    //
    // Insertion order: s1, a1, s2
    // Expected: [[s2], [a1], [s1]]
    //
    // Both sync jobs need their own batches, with async in between.
    // ──────────────────────────────────────────────────────────────
    public function testSyncTransitiveThroughAsync(): void
    {
        $graph = new Graph();
        $async = async(fn () => null);
        $sync = sync(fn () => null);
        $graph = $graph
            ->withPut('s1', $sync->withDepends('a1'))
            ->withPut('a1', $async->withDepends('s2'))
            ->withPut('s2', $sync);
        $batches = $graph->toArray();
        $this->assertBatchOrder($batches, 's1', 'a1');
        $this->assertBatchOrder($batches, 'a1', 's2');
        $expected = [['s2'], ['a1'], ['s1']];
        $this->assertSame(
            $expected,
            $batches,
            'Sync→Async→Sync chain. Got: ' . json_encode($batches)
        );
    }

    /**
     * Asserts that $job appears in a LATER batch than $dependency.
     */
    private function assertBatchOrder(array $batches, string $job, string $dependency): void
    {
        $jobBatch = null;
        $depBatch = null;
        foreach ($batches as $i => $batch) {
            if (in_array($job, $batch, true)) {
                $jobBatch = $i;
            }
            if (in_array($dependency, $batch, true)) {
                $depBatch = $i;
            }
        }
        $this->assertNotNull($jobBatch, "Job '{$job}' not found in batches: " . json_encode($batches));
        $this->assertNotNull($depBatch, "Dependency '{$dependency}' not found in batches: " . json_encode($batches));
        $this->assertGreaterThan(
            $depBatch,
            $jobBatch,
            "Job '{$job}' (batch {$jobBatch}) must come AFTER dependency '{$dependency}' (batch {$depBatch}). "
            . 'Batches: ' . json_encode($batches)
        );
    }
}
