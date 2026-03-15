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

use Chevere\Parameter\Attributes\_arrayp;
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use Chevere\Parameter\Attributes\_string;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Mermaid;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;

final class MermaidTest extends TestCase
{
    public function testJobsIO(): void
    {
        $jobs = new Jobs(
            ja: async(
                fn (): int => 1
            ),
            jb: async(
                fn (): int => 1
            )
                ->withRunIf(response('ja'))
                ->withRunIfNot(variable('var'), 1, true),
            j1: async(
                #[_return(new _arrayp(
                    id: new _int(),
                    name: new _string()
                ))]
                fn (): array => [
                    'id' => 123,
                    'name' => 'example',
                ]
            ),
            j2: sync(
                fn (int $n, string $m): int => $n + $m,
                n: response('j1', 'id'),
                m: response('j1', 'name')
            ),
            j3: sync(
                fn (int $a): int => $a,
                a: response('jb')
            ),
            j4: sync(
                fn (int $i, int $j): int => $i * $j,
                i: response('j2'),
                j: response('j3')
            ),
        );
        $this->assertSame(
            <<<MERMAID
            graph TB;
                ja("`ja`");
                j1("`j1`");
                j2("`j2`");
                jb("`jb
            *if* res(ja)
            *ifNot* var(var) 1 true`");
                j3("`j3`");
                j4("`j4`");

                j1-->|"j1->id @ j2(n:)
            j1->name @ j2(m:)"|j2;
                ja-->jb;
                jb-->|"jb @ j3(a:)"|j3;
                j2-->|"j2 @ j4(i:)"|j4;
                j3-->|"j3 @ j4(j:)"|j4;

            MERMAID,
            Mermaid::generate($jobs)->render()
        );
    }
}
