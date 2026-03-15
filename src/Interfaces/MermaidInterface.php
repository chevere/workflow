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

namespace Chevere\Workflow\Interfaces;

use JBZoo\MermaidPHP\Graph;

interface MermaidInterface
{
    /**
     * @param JobsInterface $jobs The jobs to generate the diagram for
     */
    public static function generate(JobsInterface $jobs): Graph;
}
