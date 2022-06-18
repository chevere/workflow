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

namespace Chevere\Workflow;

use Chevere\Action\Interfaces\ActionInterface;
use Chevere\DataStructure\Map;
use function Chevere\Message\message;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Ds\Map as DsMap;
use Throwable;

final class Workflow implements WorkflowInterface
{
    private ParametersInterface $parameters;

    private Map $vars;

    /**
     * @var DsMap<string, string[]>
     */
    private DsMap $expected;

    /**
     * @var DsMap<string, ParametersInterface>
     */
    private DsMap $provided;

    public function __construct(private JobsInterface $jobs)
    {
        $this->parameters = new Parameters();
        $this->vars = new Map();
        $this->expected = new DsMap();
        $this->provided = new DsMap();
        $this->putAdded(...iterator_to_array($jobs->getIterator()));
    }

    public function jobs(): JobsInterface
    {
        return $this->jobs;
    }

    public function count(): int
    {
        return $this->jobs->count();
    }

    public function vars(): Map
    {
        return $this->vars;
    }

    public function withAddedJob(JobInterface ...$jobs): WorkflowInterface
    {
        $new = clone $this;
        $new->jobs = $new->jobs->withAdded(...$jobs);
        $new->putAdded(...$jobs);

        return $new;
    }

    public function parameters(): ParametersInterface
    {
        return $this->parameters;
    }

    public function getVar(string $variable): array
    {
        try {
            // @phpstan-ignore-next-line
            return $this->vars->get($variable);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        // @phpstan-ignore-next-line
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                message('Variable %variable% not found')
                    ->withCode('%variable%', $variable)
            );
        }
    }

    public function getJobReturnArguments(string $job): ParametersInterface
    {
        try {
            return $this->provided->get($job);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        // @phpstan-ignore-next-line
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        } catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                message('Job %job% not found')
                    ->withCode('%job%', $job)
            );
        }
        // @codeCoverageIgnoreEnd
    }

    private function setParameters(string $name, JobInterface $job): void
    {
        /** @var ActionInterface $action */
        $action = new ($job->action());
        $parameters = $action->parameters();
        $this->provided->put($name, $action->responseParameters());
        foreach ($job->arguments() as $argument => $value) {
            try {
                $parameter = $parameters->get($argument);
                if ($value instanceof VariableInterface) {
                    if (!$this->parameters->has($value->name())) {
                        $this->vars = $this->vars->withPut($value->__toString(), [$value->name()]);
                    }
                    $this->putVariable($value, $parameter);
                } elseif ($value instanceof ReferenceInterface) {
                    $this->assertPreviousReference($parameter, $value);
                    $expected = $this->expected->get($value->job(), []);
                    $expected[] = $value->key();
                    $this->expected->put($value->job(), $expected);
                    $this->vars = $this->vars->withPut(
                        $value->__toString(),
                        [$value->job(), $value->key()]
                    );
                }
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    message('Incompatible declaration on Job %name% (%arg%) [%message%]')
                        ->withStrong('%name%', $name)
                        ->withStrong('%arg%', "argument@${argument}")
                        ->withStrtr('%message%', $e->getMessage()),
                    previous: $e,
                );
            }
        }
    }

    private function assertMatchesExistingParameter(
        string $name,
        ParameterInterface $existent,
        ParameterInterface $parameter
    ): void {
        if ($existent::class !== $parameter::class) {
            throw new TypeError(
                message("Reference %name% of type %type% doesn't match type %provided%")
                    ->withStrong('%name%', $name)
                    ->withCode('%type%', $existent::class)
                    ->withCode('%provided%', $parameter::class),
            );
        }
    }

    private function putVariable(
        VariableInterface $variable,
        ParameterInterface $parameter
    ): void {
        if ($this->parameters->has($variable->name())) {
            $existent = $this->parameters->get($variable->name());
            $this->assertMatchesExistingParameter(
                // @infection-ignore-all
                $variable->__toString(),
                $existent,
                $parameter
            );

            return;
        }
        $this->parameters = $this->parameters
            ->withAdded(...[
                $variable->name() => $parameter,
            ]);
    }

    private function assertPreviousReference(
        ParameterInterface $parameter,
        ReferenceInterface $reference
    ): void {
        /** @var ParametersInterface $responseParameters */
        $responseParameters = $this->provided->get($reference->job());
        if (!$responseParameters->has($reference->key())) {
            throw new OutOfBoundsException(
                message('Reference %reference% not found, response key %parameter% is not declared by %job%')
                    ->withCode('%reference%', $reference->__toString())
                    ->withStrong('%parameter%', $reference->key())
                    ->withStrong('%job%', $reference->job()),
            );
        }
        $this->assertMatchesExistingParameter(
            $reference->__toString(),
            $responseParameters->get($reference->key()),
            $parameter
        );
    }

    private function putAdded(JobInterface ...$jobs): void
    {
        foreach ($jobs as $name => $job) {
            $name = strval($name);
            $this->setParameters($name, $job);
        }
    }
}
