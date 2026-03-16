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

use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\MermaidInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;

final class Mermaid implements MermaidInterface
{
    private JobInterface $currentJob;

    private string $currentTitle;

    /**
     * @var string[]
     */
    private array $links = [];

    public function __construct()
    {
        $this->currentTitle = '';
        $this->links = [];
    }

    public static function generate(WorkflowInterface $workflow): string
    {
        $output = <<<MERMAID
        graph TB;

        MERMAID;
        $self = new self();
        foreach ($workflow->jobs()->graph()->toArray() as $jobNames) {
            foreach ($jobNames as $name) {
                $self->currentJob = $workflow->jobs()->get($name);
                $self->currentTitle = $name;
                $self->addConditions('if');
                $self->addConditions('ifNot');
                $output .= <<<MERMAID
                    {$name}("`{$self->currentTitle}`");

                MERMAID;
                $self->addLinks($name);
            }
        }
        $output .= "\n" . implode("\n", $self->links) . "\n";

        return $output;
    }

    private function addLinks(string $name): void
    {
        foreach ($this->currentJob->dependencies() as $dependency) {
            $relationParts = [];
            foreach ($this->currentJob->arguments() as $k => $v) {
                if (! ($v instanceof ResponseReferenceInterface) || $v->job() !== $dependency) {
                    continue;
                }
                $relationParts[] = <<<MARKDOWN
                {$v} @ {$name}({$k}:)
                MARKDOWN;
            }
            if ($relationParts === []) {
                $this->links[] = <<<MERMAID
                    {$dependency}-->{$name};
                MERMAID;

                continue;
            }
            $relation = implode("\n", $relationParts);
            $this->links[] = <<<MERMAID
                {$dependency}-->|"{$relation}"|{$name};
            MERMAID;
        }
    }

    private function addConditions(string $label): void
    {
        $vector = $label === 'if'
            ? $this->currentJob->runIf()
            : $this->currentJob->runIfNot();
        if (count($vector) === 0) {
            return;
        }
        $conditions = [];
        foreach ($vector as $condition) {
            $readable = match (true) {
                $condition instanceof ResponseReferenceInterface => "res({$condition})",
                $condition instanceof VariableInterface => "var({$condition})",
                is_callable($condition) => 'callable',
                default => var_export($condition, true),
            };
            $conditions[] = $readable;
        }
        $conditions = "*{$label}* " . implode(' ', $conditions);
        $this->currentTitle .= <<<PLAIN

        {$conditions}
        PLAIN;
    }
}
