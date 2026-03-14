# Workflow

![Chevere](chevere.svg)

[![Build](https://img.shields.io/github/actions/workflow/status/chevere/workflow/test.yml?branch=2.1&style=flat-square)](https://github.com/chevere/workflow/actions)
![Code size](https://img.shields.io/github/languages/code-size/chevere/workflow?style=flat-square)
[![Apache-2.0](https://img.shields.io/github/license/chevere/workflow?style=flat-square)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-blueviolet?style=flat-square)](https://phpstan.org/)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchevere%2Fworkflow%2F2.1)](https://dashboard.stryker-mutator.io/reports/github.com/chevere/workflow/2.1)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=alert_status)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=security_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=coverage)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![CodeFactor](https://www.codefactor.io/repository/github/chevere/workflow/badge)](https://www.codefactor.io/repository/github/chevere/workflow)

## Summary

**Chevere Workflow** is a PHP library for building and executing multi-step procedures with automatic dependency resolution. Define independent jobs that can run synchronously or asynchronously, pass data between them using typed responses, and let the engine handle execution order automatically.

**Key features:**

* **Declarative job definitions**: Define what to do, not how to orchestrate it
* **Automatic dependency graph**: Jobs execute in optimal order based on their dependencies
* **Sync and async execution**: Mix blocking and non-blocking jobs freely
* **Type-safe responses**: Access job outputs with full type safety
* **Conditional execution**: Run jobs based on variables or previous responses
* **Built-in retry policies**: Handle transient failures automatically
* **Testable**: Each job is independently testable and workflow graph can be verified

You define jobs and how they connect and depend on each other, **Chevere Workflow** figures out the execution order and runs them accordingly.

## Installing

Workflow is available through [Packagist](https://packagist.org/packages/chevere/workflow) and the repository source is at [chevere/workflow](https://github.com/chevere/workflow).

```sh
composer require chevere/workflow
```

## Quick Start

Here's a minimal example to get you started:

```php
use function Chevere\Workflow\{workflow, sync, variable, run};

// 1. Define a workflow with jobs
$workflow = workflow(
    greet: sync(
        fn(string $name): string => "Hello, {$name}!",
        name: variable('username')
    )
);

// 2. Run with variables
$result = run($workflow, username: 'World');

// 3. Get typed responses
echo $result->response('greet')->string();
// Output: Hello, World!
```

## Core Concepts

Workflow is built around four main concepts:

| Concept      | Description                                         |
| ------------ | --------------------------------------------------- |
| **Job**      | A unit of work that produces a response             |
| **Variable** | External input provided when running the workflow   |
| **Response** | Reference to output from a previous job             |
| **Graph**    | Automatic execution order based on job dependencies |

### How It Works

1. You define jobs using `sync()` or `async()` functions
2. Jobs declare their inputs: literal values, `variable()` references, or `response()` from other jobs
3. The engine builds a dependency graph automatically
4. Jobs execute in optimal order (parallel when possible)
5. Access typed responses after execution

## Functions Reference

| Function     | Purpose                                   |
| ------------ | ----------------------------------------- |
| `workflow()` | Create a workflow from named jobs         |
| `sync()`     | Create a synchronous (blocking) job       |
| `async()`    | Create an asynchronous (non-blocking) job |
| `variable()` | Declare a runtime variable                |
| `response()` | Reference another job's output            |
| `run()`      | Execute a workflow with variables         |

---

## Jobs

Jobs are the building blocks of a workflow. Each job wraps an executable unit ([Action](https://chevere.org/packages/action), [Closure](https://www.php.net/manual/en/class.closure.php), Invocable class, or any PHP [callable](https://www.php.net/manual/en/language.types.callable.php)) and declares its input arguments.

### Creating Jobs with Closures

Use closures for simple, inline operations:

```php
use function Chevere\Workflow\{workflow, sync, async, variable, response, run};

$workflow = workflow(
    // Simple calculation
    add: sync(
        fn(int $a, int $b): int => $a + $b,
        a: 10,
        b: variable('number')
    ),
    // Format the result
    format: sync(
        fn(int $sum): string => "Sum: {$sum}",
        sum: response('add')
    )
);

$result = run($workflow, number: 5);
echo $result->response('format')->string(); // Sum: 15
```

### Creating Jobs with Action Classes

For complex or reusable logic, use [Action](https://chevere.org/packages/action) classes as these additionally support method definitions for `acceptParameters()` and `acceptReturn()` to define parameter and return rules that are automatically applied at runtime.

```php
use Chevere\Action\Action;

class FetchUser extends Action
{
    public function __invoke(int $userId): array
    {
        // Fetch user from database
        return ['id' => $userId, 'name' => 'John', 'email' => 'john@example.com'];
    }
}

class SendEmail extends Action
{
    public function __invoke(string $email, string $subject): bool
    {
        // Send email logic
        return true;
    }
}
```

```php
$workflow = workflow(
    user: sync(
        FetchUser::class,
        userId: variable('id')
    ),
    notify: sync(
        SendEmail::class,
        email: response('user', 'email'),
        subject: 'Welcome!'
    )
);

$result = run($workflow, id: 123);
```

### Creating Jobs with Invocable Classes

Use invocable classes (classes with `__invoke` method) for reusable logic without needing Action base class:

```php
class CalculateTotal
{
    public function __invoke(array $items, float $taxRate): float
    {
        $subtotal = array_sum(array_column($items, 'price'));
        return $subtotal * (1 + $taxRate);
    }
}

class FormatCurrency
{
    public function __invoke(float $amount, string $currency = 'USD'): string
    {
        return $currency . ' ' . number_format($amount, 2);
    }
}
```

```php
$workflow = workflow(
    total: sync(
        CalculateTotal::class,
        items: variable('items'),
        taxRate: 0.08
    ),
    formatted: sync(
        FormatCurrency::class,
        amount: response('total'),
        currency: 'EUR'
    )
);

$result = run($workflow, items: [
    ['name' => 'Item 1', 'price' => 10.00],
    ['name' => 'Item 2', 'price' => 20.00]
]);
echo $result->response('formatted')->string(); // EUR 32.40
```

### Creating Jobs with Callables

Use any PHP callable including array callbacks, function names, or static methods:

```php
class StringHelper
{
    public static function uppercase(string $text): string
    {
        return strtoupper($text);
    }

    public function reverse(string $text): string
    {
        return strrev($text);
    }
}
```

```php
$helper = new StringHelper();

$workflow = workflow(
    // Using built-in PHP function
    trim: sync(
        'trim',
        string: variable('input')
    ),
    // Using static method
    upper: sync(
        [StringHelper::class, 'uppercase'],
        text: response('trim')
    ),
    // Using instance method
    reversed: sync(
        [$helper, 'reverse'],
        text: response('upper')
    )
);

$result = run($workflow, input: '  hello  ');
echo $result->response('reversed')->string(); // OLLEH
```

### Sync vs Async Jobs

**Synchronous jobs** (`sync`) block execution until complete. Use for operations that must run in sequence:

```php
workflow(
    first: sync(ActionA::class),  // Runs first
    second: sync(ActionB::class), // Waits for first
    third: sync(ActionC::class),  // Waits for second
);
// Graph: first → second → third
```

**Asynchronous jobs** (`async`) run concurrently when they have no dependencies:

```php
workflow(
    resize1: async(ResizeImage::class, size: 'thumb'),
    resize2: async(ResizeImage::class, size: 'medium'),
    resize3: async(ResizeImage::class, size: 'large'),
    store: sync(StoreFiles::class, files: response('resize1'), ...)
);
// Graph: [resize1, resize2, resize3] → store
```

### Job Arguments

Jobs accept three types of arguments:

```php
workflow(
    example: sync(
        MyAction::class,
        literal: 'fixed value',           // Literal value
        dynamic: variable('userInput'),   // Runtime variable
        chained: response('otherJob'),    // Previous job output
    )
);
```

Jobs can define I/O rules via [chevere/parameter](https://chevere.org/packages/parameter). Workflow derives parameter and return definitions from the callable signature or Action reflection and validates inputs and responses at runtime.

### Integration with Chevere

Workflow works seamlessly with the `chevere/parameter` and `chevere/action` packages. When you declare parameter rules with `chevere/parameter` (types, ranges, or custom validators), those rules travel with the job definitions and are applied automatically at runtime. Workflow performs the validation layer for you before invoking jobs so callers don't need to repeat input or response checks. The same integration applies to `chevere/action` Action classes: parameter and return definitions are derived from action signatures and validated by Workflow.

These integrations are optional extras. You do not have to use `chevere/parameter` or `chevere/action` to use Workflow, but opting in gives stronger guarantees and reduces validation boilerplate across jobs.

---

## Variables

Variables are placeholders for values provided at runtime. Declare them with `variable()`:

```php
$workflow = workflow(
    job1: sync(
        SomeAction::class,
        name: variable('userName'),
        age: variable('userAge')
    )
);

// Provide values when running
$result = run($workflow, userName: 'Alice', userAge: 30);
```

All declared variables must be provided when running the workflow.

---

## Responses

Use `response()` to pass output from one job to another. This automatically establishes a dependency.

```php
$workflow = workflow(
    fetch: sync(
        FetchData::class,
        url: variable('endpoint')
    ),
    process: sync(
        ProcessData::class,
        data: response('fetch')  // Gets entire response from 'fetch'
    ),
    extract: sync(
        ExtractField::class,
        value: response('fetch', 'items')  // Gets 'items' key from response
    )
);
```

### Accessing Nested Response Keys/Properties

When a job returns `array` or `object`, access specific keys/properties directly in `response()`:

```php
response('user')           // job:user       Entire response object
response('user', 'id')     // job:user->id   id property from object response
response('post', 'id')     // job:post['id'] id key from array response
```

---

## Execution Graph

The workflow engine automatically builds an execution graph based on job dependencies. Jobs without dependencies run in parallel (when using `async`), while dependent jobs wait for their dependencies.

```php
$workflow = workflow(
    // Independent async jobs run in parallel
    thumb: async(ImageResize::class, size: 'thumb', file: variable('image')),
    medium: async(ImageResize::class, size: 'medium', file: variable('image')),
    large: async(ImageResize::class, size: 'large', file: variable('image')),
    // Sync job waits for all above
    store: sync(
        StoreFiles::class,
        thumb: response('thumb'),
        medium: response('medium'),
        large: response('large')
    )
);

// View the execution graph
$graph = $workflow->jobs()->graph()->toArray();
// [
//     ['thumb', 'medium', 'large'],  // Level 0: parallel
//     ['store']                       // Level 1: after dependencies
// ]
```

```mermaid
graph TD
    thumb --> store
    medium --> store
    large --> store
```

---

## Running Workflows

Execute a workflow with the `run()` function:

```php
use function Chevere\Workflow\run;

// Basic execution
$result = run($workflow, var1: 'value1', var2: 'value2');

// With dependency injection container
$result = run($workflow, $container, var1: 'value1');
```

### Accessing Responses

The run result provides type-safe access to job responses:

```php
$result = run($workflow, ...);

// Get typed responses
$result->response('jobName')->string();     // string
$result->response('jobName')->int();        // int
$result->response('jobName')->float();      // float
$result->response('jobName')->bool();       // bool
$result->response('jobName')->array();      // array

// Access array keys directly
$result->response('jobName', 'key')->string();
$result->response('jobName', 'nested', 'key')->int();
```

### Check Skipped Jobs

When using conditional execution, check which jobs were skipped:

```php
if ($result->skip()->contains('optionalJob')) {
    // Job was skipped
}
```

---

## Dependency Injection

Workflow supports automatic dependency injection for any class passed as a class-string using any [PSR-11](https://www.php-fig.org/psr/psr-11/) compatible container. When your jobs reference classes with constructor dependencies, you can provide a container that will automatically resolve and inject those dependencies. [chevere/container](https://chevere.org/packages/container) is one example, but any PSR-11 container works.

### Passing a Container

Pass a `ContainerInterface` instance as the second argument to `run()`:

```php
use Chevere\Container\Container; // or any PSR-11 container
use function Chevere\Workflow\run;

// Create container with dependencies
$container = new Container(
    logger: new Logger(),
    database: new Database()
);

// Run workflow with container
$result = run($workflow, $container, ...$vars);
```

When a job references a class-string (Action class, invokable class, or any other class), Workflow uses the container to:

1. **Auto-inject dependencies** - Automatically resolve constructor parameters from the container
2. **Validate availability** - Ensure all required dependencies are present before execution
3. **Support nested dependencies** - Recursively resolve dependencies of dependencies

**Note:** Dependency injection only works for classes passed as class-strings (e.g., `MyClass::class`). It does not work for closures, already instantiated objects, or array callbacks.

### Example with Action Dependencies

```php
use Chevere\Action\Action;

class SendNotification extends Action
{
    // Dependencies injected automatically
    public function __construct(
        private LoggerInterface $logger,
        private MailerInterface $mailer
    ) {}

    public function __invoke(string $email, string $message): bool
    {
        $this->logger->info("Sending email to {$email}");
        return $this->mailer->send($email, $message);
    }
}

// Provide dependencies in container
$container = new Container(
    logger: new ConsoleLogger(),
    mailer: new SmtpMailer()
);

$workflow = workflow(
    notify: sync(
        SendNotification::class,  // Dependencies auto-injected
        email: variable('userEmail'),
        message: 'Welcome!'
    )
);

$result = run($workflow, $container, userEmail: 'user@example.com');
```

### Example with Invokable Class Dependencies

Dependency injection also works with invokable classes and any other class:

```php
class ProcessOrder
{
    // Dependencies injected automatically
    public function __construct(
        private DatabaseInterface $database,
        private PaymentGateway $payment
    ) {}

    public function __invoke(int $orderId, float $amount): array
    {
        $order = $this->database->getOrder($orderId);
        $result = $this->payment->charge($amount);
        return ['order' => $order, 'payment' => $result];
    }
}

// Provide dependencies in container
$container = new Container(
    database: new MySQLDatabase(),
    payment: new StripeGateway()
);

$workflow = workflow(
    process: sync(
        ProcessOrder::class,  // Dependencies auto-injected
        orderId: variable('orderId'),
        amount: variable('amount')
    )
);

$result = run($workflow, $container, orderId: 123, amount: 99.99);
```

---

## Conditional Execution

Control whether a job runs using `withRunIf()` (run when conditions are met) or `withRunIfNot()` (skip when conditions are met). Both methods accept the same kinds of conditions and are evaluated at run-time.

### Accepted condition types

* `boolean` literal — evaluated directly
* `variable('name')` — runtime argument coerced to boolean
* `response('job')` or `response('job', 'key')` — uses another job's output
* `callable` — invokes a callable passing the current `RunInterface` context argument

---

```php
use function Chevere\Workflow\{workflow, sync, variable, run, response};

$workflow = workflow(
    isTooBig: sync(
        fn(string $path, int $maxBytes): bool => filesize($path) > $maxBytes,
        path: variable('file'),
        maxBytes: variable('maxBytes')
    ),
    compress: sync(
        CompressImage::class,
        file: variable('file')
    )->withRunIf(
        true,                       // literal
        variable('shouldCompress'), // workflow variable
        response('isTooBig'),       // job response value
        fn(RunInterface $run) => $run->variable('shouldCompress')->bool(), // closure condition variable
        fn(RunInterface $run) => $run->response('isTooBig')->bool(), // closure condition using response
    )
);
$result = run($workflow,
    file: '/path/to/image.jpg',
    shouldCompress: true,
    maxBytes: 1_000_000
);
```

---

## Explicit Dependencies

While `response()` creates implicit dependencies, use `withDepends()` for explicit control:

```php
$workflow = workflow(
    setup: async(SetupAction::class),
    process: async(
        ProcessAction::class,
        data: variable('input')
    )->withDepends('setup')  // Wait for setup even without using its response
);
```

---

## Retry Policy

Configure automatic retries for jobs that may fail transiently:

```php
$workflow = workflow(
    fetch: sync(
        FetchFromApi::class,
        url: variable('endpoint')
    )->withRetry(
        timeout: 300,     // Max 300 seconds total
        maxAttempts: 5,   // Try up to 5 times
        delay: 10         // Wait 10 seconds between attempts
    )
);
```

| Parameter     | Type          | Default | Description                                   |
| ------------- | ------------- | ------- | --------------------------------------------- |
| `timeout`     | `int<0, max>` | `0`     | Max execution time in seconds (0 = unlimited) |
| `maxAttempts` | `int<1, max>` | `1`     | Total attempts including initial              |
| `delay`       | `int<0, max>` | `0`     | Seconds between retries (0 = immediate)       |

Retry delays use non-blocking sleep, making them safe for async runtimes.

---

## Exception Handling

When a job fails, a `WorkflowException` wraps the original exception:

```php
use Chevere\Workflow\Exceptions\WorkflowException;

try {
    $result = run($workflow, ...);
} catch (WorkflowException $e) {
    echo $e->name;        // Name of the failed job
    echo $e->job;         // Job instance
    echo $e->throwable;   // Original exception
}
```

---

## Using WorkflowTrait

For class-based workflow management, use `WorkflowTrait`:

```php
use Chevere\Workflow\Traits\WorkflowTrait;
use function Chevere\Workflow\{workflow, sync, variable};

class OrderProcessor
{
    use WorkflowTrait;

    public function process(int $orderId): void
    {
        $workflow = workflow(
            validate: sync(ValidateOrder::class, id: variable('orderId')),
            charge: sync(ChargePayment::class, order: response('validate')),
            fulfill: sync(FulfillOrder::class, order: response('charge'))
        );

        $this->execute($workflow, orderId: $orderId);
    }

    public function getResult(): string
    {
        return $this->run()->response('fulfill')->string();
    }
}
```

---

## Testing

### Testing Actions

Test your Action classes independently:

```php
use PHPUnit\Framework\TestCase;

class FetchUserTest extends TestCase
{
    public function testFetchUser(): void
    {
        $action = new FetchUser();
        $result = $action(userId: 123);

        $this->assertSame(123, $result['id']);
        $this->assertArrayHasKey('name', $result);
    }
}
```

### Testing Workflow Graph

Verify execution order:

```php
public function testWorkflowGraph(): void
{
    $workflow = workflow(
        a: async(ActionA::class),
        b: async(ActionB::class),
        c: sync(ActionC::class, x: response('a'), y: response('b'))
    );

    $graph = $workflow->jobs()->graph()->toArray();

    $this->assertSame([['a', 'b'], ['c']], $graph);
}
```

### Testing Responses

Test complete workflow execution:

```php
public function testWorkflowResponses(): void
{
    $result = run($workflow, input: 'test');

    $this->assertSame('expected', $result->response('job1')->string());
    $this->assertSame(42, $result->response('job2', 'count')->int());
}
```

### Testing Exceptions

Use `ExpectWorkflowExceptionTrait` for error scenarios:

```php
use Chevere\Workflow\Traits\ExpectWorkflowExceptionTrait;

class WorkflowExceptionTest extends TestCase
{
    use ExpectWorkflowExceptionTrait;

    public function testJobFailure(): void
    {
        $this->expectWorkflowException(
            closure: fn() => run($workflow, input: 'invalid'),
            exception: InvalidArgumentException::class,
            job: 'validate',
            message: 'Invalid input provided'
        );
    }
}
```

---

## Real-World Examples

### Image Processing Pipeline

```php
$workflow = workflow(
    // Parallel image resizing
    thumb: async(
        ImageResize::class,
        file: variable('image'),
        width: 150,
        height: 150
    ),
    medium: async(
        ImageResize::class,
        file: variable('image'),
        width: 800
    ),
    // Store after all resizing completes
    store: sync(
        StoreFiles::class,
        thumb: response('thumb'),
        medium: response('medium'),
        directory: variable('outputDir')
    )
);

$result = run($workflow,
    image: '/uploads/photo.jpg',
    outputDir: '/processed/'
);
```

### User Registration Flow

```php
$workflow = workflow(
    validate: sync(
        ValidateRegistration::class,
        email: variable('email'),
        password: variable('password')
    ),
    createUser: sync(
        CreateUser::class,
        data: response('validate')
    ),
    sendWelcome: async(
        SendWelcomeEmail::class,
        user: response('createUser')
    ),
    logEvent: async(
        LogRegistration::class,
        userId: response('createUser', 'id')
    )
);
```

### Conditional Processing

```php
$workflow = workflow(
    analyze: sync(
        AnalyzeContent::class,
        content: variable('text')
    ),
    translate: sync(
        TranslateContent::class,
        text: variable('text'),
        targetLang: variable('lang')
    )->withRunIf(
        variable('needsTranslation')
    ),
    publish: sync(
        PublishContent::class,
        content: response('analyze'),
        translated: response('translate')
    )
);

$result = run($workflow,
    text: 'Hello world',
    lang: 'es',
    needsTranslation: true
);
```

---

## Demo

Run the included examples:

```sh
php demo/hello-world.php          # Basic workflow
php demo/chevere.php              # Chained jobs
php demo/closure.php              # Using closures
php demo/sync-vs-async.php        # Performance comparison
php demo/image-resize.php         # Parallel processing
php demo/run-if.php               # Conditional execution
```

See the [demo](demo) directory for all examples.

## Documentation

Documentation is available at [chevere.org/packages/workflow](https://chevere.org/packages/workflow).

For a comprehensive introduction, read [Workflow for PHP](https://rodolfoberrios.com/2022/04/09/workflow-php/) on Rodolfo's blog.

## License

Copyright [Rodolfo Berrios A.](https://rodolfoberrios.com/)

This software is licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE) for the full license text.

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
