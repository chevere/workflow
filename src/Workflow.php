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
use Chevere\Message\Message;
use function Chevere\Message\message;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Ds\Map as DsMap;
use function Safe\preg_match;
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
                (new Message('Variable %variable% not found'))
                    ->code('%variable%', $variable)
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
                (new Message('Job %job% not found'))
                    ->code('%job%', $job)
            );
        }
        // @codeCoverageIgnoreEnd
    }

    private function setParameters(string $name, JobInterface $job): void
    {
        $action = $job->action();
        /** @var ActionInterface $action */
        $action = new $action();
        $parameters = $action->parameters();
        $this->provided->put($name, $action->responseParameters());
        foreach ($job->arguments() as $argument => $value) {
            try {
                if (!is_string($value)) {
                    continue;
                }
                $parameter = $parameters->get($argument);
                if (preg_match(self::REGEX_VARIABLE_REFERENCE, $value, $matches) !== 0) {
                    /** @var string[] $matches */
                    if (!$this->parameters->has($matches[1])) {
                        $this->vars = $this->vars->withPut($value, [$matches[1]]);
                    }
                    $this->putParameter($matches[1], $parameter);
                } elseif (preg_match(JobInterface::REGEX_JOB_RESPONSE_REFERENCE, $value, $matches) !== 0) {
                    /** @var string[] $matches */
                    $previousJob = strval($matches[1]);
                    $previousResponseKey = strval($matches[2]);
                    $this->assertPreviousReference($parameter, $previousJob, $previousResponseKey);
                    $expected = $this->expected->get($previousJob, []);
                    $expected[] = $previousResponseKey;
                    $this->expected->put($previousJob, $expected);
                    $this->vars = $this->vars->withPut(
                        $value,
                        [$previousJob, $previousResponseKey]
                    );
                }
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    message('Incompatible declaration on Job %name% (%arg%) [%message%]')
                        ->strong('%name%', $name)
                        ->strong('%arg%', "argument@${argument}")
                        ->strtr('%message%', $e->getMessage()),
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
                    ->strong('%name%', $name)
                    ->code('%type%', $existent::class)
                    ->code('%provided%', $parameter::class),
            );
        }
    }

    private function putParameter(
        string $name,
        ParameterInterface $parameter
    ): void {
        if ($this->parameters->has($name)) {
            $existent = $this->parameters->get($name);
            $this->assertMatchesExistingParameter(
                // @infection-ignore-all
                '${' . $name . '}',
                $existent,
                $parameter
            );

            return;
        }
        $this->parameters = $this->parameters
            ->withAdded(...[
                $name => $parameter,
            ]);
    }

    private function assertPreviousReference(
        ParameterInterface $parameter,
        string $previousJob,
        string $responseKey
    ): void {
        $reference = '${' . "${previousJob}:${responseKey}" . '}';
        /** @var ParametersInterface $responseParameters */
        $responseParameters = $this->provided->get($previousJob);
        if (!$responseParameters->has($responseKey)) {
            throw new OutOfBoundsException(
                message('Reference %reference% not found, response parameter %parameter% is not declared by %job%')
                    ->code('%reference%', $reference)
                    ->strong('%parameter%', $responseKey)
                    ->strong('%job%', $previousJob),
            );
        }
        $this->assertMatchesExistingParameter(
            $reference,
            $responseParameters->get($responseKey),
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
