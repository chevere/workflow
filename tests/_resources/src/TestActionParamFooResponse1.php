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
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Parameter\StringParameter;

class TestActionParamFooResponse1 extends Action
{
    public function getResponseParameters(): ParametersInterface
    {
        return new Parameters(response1: new StringParameter());
    }

    public function run(string $foo): array
    {
        return [
            'response1' => $foo,
        ];
    }
}
