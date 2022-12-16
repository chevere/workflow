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
use function Chevere\Parameter\booleanParameter;
use Chevere\Parameter\Interfaces\ParametersInterface;
use function Chevere\Parameter\parameters;

final class TestActionNoParamsBooleanResponses extends Action
{
    public function getResponseParameters(): ParametersInterface
    {
        return parameters(
            true: booleanParameter(),
            false: booleanParameter(),
        );
    }

    public function run(): array
    {
        return [
            'true' => true,
            'false' => true,
        ];
    }
}
