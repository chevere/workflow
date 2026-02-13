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

namespace Chevere\Tests\src;

use Chevere\Action\Action;
use Chevere\Parameter\Attributes\_arrayp;
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use LogicException;

final class TestActionWorksOnNAttempt extends Action
{
    private int $attemptCount = 0;

    public function __construct(
        private int $successOnAttempt
    ) {
    }

    #[_return(
        new _arrayp(
            attempt: new _int(),
        )
    )]
    public function __invoke(): array
    {
        ++$this->attemptCount;

        if ($this->attemptCount < $this->successOnAttempt) {
            $attempts = $this->attemptCount;

            throw new LogicException(
                "Attempt {$attempts} failed, required attempt {$this->successOnAttempt}"
            );
        }

        return [
            'attempt' => $this->attemptCount,
        ];
    }
}
