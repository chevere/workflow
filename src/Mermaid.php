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

use Chevere\DataStructure\Map;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\MermaidInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;

final class Mermaid implements MermaidInterface
{
    private JobInterface $currentJob;

    private Graph $graph;

    /**
     * @var Map<Node>
     */
    private Map $nodes;

    private string $currentTitle;

    public function __construct()
    {
        $this->graph = new Graph(
            [
                'title' => 'Workflow',
            ]
        );
        $this->nodes = new Map();
        $this->currentTitle = '';
    }

    public static function generate(JobsInterface $jobs): Graph
    {
        $self = new self();
        foreach ($jobs->graph()->toArray() as $jobNames) {
            foreach ($jobNames as $name) {
                $self->currentJob = $jobs->get($name);
                $self->currentTitle = $name;
                $self->addConditions('if');
                $self->addConditions('ifNot');
                $node = new Node($name, "`{$self->currentTitle}`");
                $self->nodes = $self->nodes->withPut($name, $node);
                $self->graph->addNode($node);
                $self->addLinks($name);
            }
        }

        return $self->graph;
    }

    private function addLinks(string $name): void
    {
        $node = $this->nodes->get($name);
        foreach ($this->currentJob->dependencies() as $dependency) {
            $relation = '';
            foreach ($this->currentJob->arguments() as $k => $v) {
                if (! ($v instanceof ResponseReferenceInterface) || $v->job() !== $dependency) {
                    continue;
                }
                $relation .= <<<PLAIN
                {$v} @ {$name}({$k}:)

                PLAIN;
            }
            $this->graph->addLink(
                new Link($this->nodes->get($dependency), $node, $relation)
            );
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
