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

namespace Chevere\Workflow\Traits;

use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Caller\Interfaces\CallerInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Workflow\Config;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RetryPolicyInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Closure;

trait JobPropertiesTrait
{
    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $dependencies;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $after;

    private ParametersInterface $parameters;

    private ParameterInterface $return;

    /**
     * @var VectorInterface<ResponseReferenceInterface|VariableInterface>
     */
    private VectorInterface $runIf;

    /**
     * @var VectorInterface<ResponseReferenceInterface|VariableInterface>
     */
    private VectorInterface $runIfNot;

    private bool $isSync;

    private CallerInterface $caller;

    private RetryPolicyInterface $retryPolicy;

    /**
     * @var ActionInterface|class-string|Closure
     */
    private ActionInterface|string|Closure $_;

    private Config $config;

    private VectorInterface $violations;

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function violations(): VectorInterface
    {
        return $this->violations;
    }

    public function caller(): CallerInterface
    {
        return $this->caller;
    }

    public function parameters(): ParametersInterface
    {
        return $this->parameters;
    }

    public function return(): ParameterInterface
    {
        return $this->return;
    }

    public function retryPolicy(): RetryPolicyInterface
    {
        return $this->retryPolicy;
    }

    public function action(): ActionInterface|string|Closure
    {
        return $this->_;
    }

    /**
     * @return VectorInterface<string>
     */
    public function dependencies(): VectorInterface
    {
        return $this->dependencies;
    }

    /**
     * @return VectorInterface<string>
     */
    public function after(): VectorInterface
    {
        return $this->after;
    }

    /**
     * @return VectorInterface<ResponseReferenceInterface|VariableInterface|callable|bool>
     */
    public function runIf(): VectorInterface
    {
        return $this->runIf;
    }

    /**
     * @return VectorInterface<ResponseReferenceInterface|VariableInterface|callable|bool>
     */
    public function runIfNot(): VectorInterface
    {
        return $this->runIfNot;
    }

    public function isSync(): bool
    {
        return $this->isSync;
    }
}
