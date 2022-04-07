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

namespace Chevere\Tests\_resources\src;

use Chevere\Action\Action;
use function Chevere\Parameter\integerParameter;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;

final class TestAction extends Action
{
    public function getDescription(): string
    {
        return 'test';
    }

    public function getResponseParameters(): ParametersInterface
    {
        return new Parameters(id: integerParameter());
    }

    public function run(): array
    {
        return [
            'id' => 123
        ];
    }
}
