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

namespace Chevere\Tests;

use ArrayIterator;
use Chevere\Tests\src\TestWorkflowProviderLintMode;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class WorkflowLintSchemaTest extends TestCase
{
    private static string $schemaPath = __DIR__ . '/../schema/workflow-lint.schema.json';

    protected function setUp(): void
    {
        putenv('CHEVERE_WORKFLOW_LINT_ENABLE=1');
    }

    protected function tearDown(): void
    {
        putenv('CHEVERE_WORKFLOW_LINT_ENABLE');
    }

    public function testLintOutputMatchesSchema(): void
    {
        $lint = json_decode(
            TestWorkflowProviderLintMode::workflow()->lint()
        );
        $schema = json_decode(
            file_get_contents(self::$schemaPath)
        );
        $validator = new Validator();
        $result = $validator->validate($lint, $schema);
        $this->assertTrue(
            $result->isValid(),
            implode("\n", array_map(
                fn ($error) => $error->message(),
                iterator_to_array($result->error()?->getLeafErrors() ?? new ArrayIterator())
            ))
        );
    }
}
