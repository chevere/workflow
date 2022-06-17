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

use Chevere\Str\Exceptions\StrCtypeDigitException;
use Chevere\Str\Exceptions\StrCtypeSpaceException;
use Chevere\Str\Exceptions\StrEmptyException;
use Chevere\Tests\_resources\src\TestAction;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Graph;
use Chevere\Workflow\Interfaces\JobInterface;
use function Chevere\Workflow\job;
use PHPUnit\Framework\TestCase;

final class GraphTest extends TestCase
{
    private function getJob(): JobInterface
    {
        return job(TestAction::class);
    }

    public function testEmpty(): void
    {
        $graph = new Graph();
        $this->expectException(OutOfBoundsException::class);
        $graph->hasDependencies('j0');
    }

    public function testWithPut(): void
    {
        $graph = new Graph();
        $this->assertSame([], $graph->toArray());
        $graph = $graph->withPut('j0', $this->getJob()->withDepends('j1'));
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', $this->getJob()->withDepends('j2'));
        $this->assertSame(
            [
                0 => ['j1', 'j2'],
                1 => ['j0'],
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j1', $this->getJob());
        $graph = $graph->withPut('j2', $this->getJob());
        $this->assertSame(
            [
                0 => ['j1', 'j2'],
                1 => ['j0']
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j2', $this->getJob()->withDepends('j0'));
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j0'],
                2 => ['j2']
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j1', $this->getJob()->withDepends('j0'));
        $this->assertSame(
            [
                0 => ['j0'],
                1 => ['j1', 'j2'],
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j0', $this->getJob()->withDepends('j1'));
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j0'],
                2 => ['j2'],
            ],
            $graph->toArray()
        );
        $graph = $graph->withPut('j0', $this->getJob()->withDepends('j2'));
        $this->assertSame(
            [
                0 => ['j1', 'j2'],
                1 => ['j0'],
            ],
            $graph->toArray()
        );
    }

    public function testWithPutSync(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut(
            'j2',
            $this->getJob()->withDepends('j0', 'j1')
        );
        $graph = $graph->withPut('j1', $this->getJob()->withIsSync());
        $this->assertSame(
            [
                0 => ['j0'],
                1 => ['j1'],
                2 => ['j2'],
            ],
            $graph->toArray()
        );
    }

    public function testWithPutSyncFirst(): void
    {
        $graph = new Graph();
        $graph = $graph->withPut(
            'j2',
            $this->getJob()->withDepends('j0', 'j1')
        );
        $graph = $graph->withPut('j0', $this->getJob()->withIsSync());
        $this->assertSame(
            [
                0 => ['j0'],
                1 => ['j1'],
                2 => ['j2'],
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
            0 => ['j0', 'j1'],
            1 => ['j2'],
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
        $this->expectException(StrEmptyException::class);
        $graph->withPut('job', $this->getJob()->withDepends(''));
    }

    public function testWithPutSpace(): void
    {
        $graph = new Graph();
        $this->expectException(StrCtypeSpaceException::class);
        $graph->withPut('job', $this->getJob()->withDepends(' '));
    }

    public function testWithPutDigit(): void
    {
        $graph = new Graph();
        $this->expectException(StrCtypeDigitException::class);
        $graph->withPut('job', $this->getJob()->withDepends('123'));
    }

    public function _testPodcast(): void
    {
        $graph = new Graph();
        $graph = $graph
            ->withPut(
                'ReleaseOnTransistorFM',
                $this->getJob()->withDepends(
                    'ProcessPodcast',
                    'OptimizePodcast'
                )
            )
            ->withPut(
                'ReleaseOnApplePodcasts',
                $this->getJob()->withDepends(
                    'ProcessPodcast',
                    'OptimizePodcast'
                )
            )
            ->withPut(
                'CreateAudioTranscription',
                $this->getJob()->withDepends(
                    'ProcessPodcast'
                )
            )
            ->withPut(
                'TranslateAudioTranscription',
                $this->getJob()->withDepends(
                    'CreateAudioTranscription'
                )
            )
            ->withPut(
                'NotifySubscribers',
                $this->getJob()->withDepends(
                    'ReleaseOnTransistorFM',
                    'ReleaseOnApplePodcasts'
                )
            )
            ->withPut(
                'SendTweetAboutNewPodcast',
                $this->getJob()->withDepends(
                    'TranslateAudioTranscription',
                    'ReleaseOnTransistorFM',
                    'ReleaseOnApplePodcasts'
                )
            );
        $expected = [
            0 => [
                'ProcessPodcast',
                'OptimizePodcast'
            ],
            1 => [
                'CreateAudioTranscription',
                'ReleaseOnTransistorFM',
                'ReleaseOnApplePodcasts'
            ],
            2 => [
                'TranslateAudioTranscription',
                'NotifySubscribers'
            ],
            3 => [
                'SendTweetAboutNewPodcast'
            ],
        ];
        $this->assertSame($expected, $graph->toArray());
    }
}
