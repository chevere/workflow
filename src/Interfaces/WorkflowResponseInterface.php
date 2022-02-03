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

use Chevere\Response\Interfaces\ResponseInterface;

/**
 * Describes the component in charge of providing a workflow response.
 */
interface WorkflowResponseInterface extends ResponseInterface
{
    /**
     * Return an instance with the specified workflow message.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified workflow message.
     */
    public function withWorkflowMessage(WorkflowMessageInterface $workflowMessage): self;

    public function workflowMessage(): WorkflowMessageInterface;
}
