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
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\JobsGraph;
use PHPUnit\Framework\TestCase;

final class JobsGraphTest extends TestCase
{
    public function testEmpty(): void
    {
        $graph = new JobsGraph();
        $this->expectException(OutOfBoundsException::class);
        $graph->hasDependencies('j0');
    }

    public function testWithPut(): void
    {
        $graph = new JobsGraph();
        $expected = [];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', 'j1');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', 'j1');
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', 'j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j1');
        $graph = $graph->withPut('j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0']
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j2', 'j0');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
            2 => ['j2']
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j1', 'j0');
        $expected = [
            0 => ['j0'],
            1 => ['j1', 'j2'],
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', 'j1');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
            2 => ['j2'],
        ];
        $this->assertSame($expected, $graph->toArray());
        $graph = $graph->withPut('j0', 'j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $graph->toArray());
    }

    public function testWithPutWea(): void
    {
        $graph = new JobsGraph();
        $graph = $graph->withPut('j0');
        $this->assertTrue($graph->hasDependencies('j0'));
        $this->assertFalse($graph->hasDependencies('j0', 'jn'));
        $this->assertSame([], $graph->get('j0')->toArray());
        $graph = $graph->withPut('j1');
        $graph = $graph->withPut('j2', 'j0');
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
        $graph = new JobsGraph();
        $this->expectException(InvalidArgumentException::class);
        $graph->withPut('j0', 'j0');
    }

    public function testWithPutDupes(): void
    {
        $graph = new JobsGraph();
        $this->expectException(OverflowException::class);
        $graph->withPut('j0', 'j1', 'j1');
    }

    public function testWithPutEmpty(): void
    {
        $graph = new JobsGraph();
        $this->expectException(StrEmptyException::class);
        $graph->withPut('job', '');
    }

    public function testWithPutSpace(): void
    {
        $graph = new JobsGraph();
        $this->expectException(StrCtypeSpaceException::class);
        $graph->withPut('job', ' ');
    }

    public function testWithPutDigit(): void
    {
        $graph = new JobsGraph();
        $this->expectException(StrCtypeDigitException::class);
        $graph->withPut('job', '123');
    }
    
    public function testPodcast(): void
    {
        $graph = new JobsGraph();
        $graph = $graph
            ->withPut(
                'ReleaseOnTransistorFM',
                'ProcessPodcast',
                'OptimizePodcast'
            )
            ->withPut(
                'ReleaseOnApplePodcasts',
                'ProcessPodcast',
                'OptimizePodcast'
            )
            ->withPut(
                'CreateAudioTranscription',
                'ProcessPodcast'
            )
            ->withPut(
                'TranslateAudioTranscription',
                'CreateAudioTranscription'
            )
            ->withPut(
                'NotifySubscribers',
                'ReleaseOnTransistorFM',
                'ReleaseOnApplePodcasts'
            )
            ->withPut(
                'SendTweetAboutNewPodcast',
                'TranslateAudioTranscription',
                'ReleaseOnTransistorFM',
                'ReleaseOnApplePodcasts'
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
