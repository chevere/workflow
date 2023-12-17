# Workflow

> 🔔 Subscribe to the [newsletter](https://chv.to/chevere-newsletter) to don't miss any update regarding Chevere.

![Chevere](chevere.svg)

[![Build](https://img.shields.io/github/actions/workflow/status/chevere/workflow/test.yml?branch=0.9&style=flat-square)](https://github.com/chevere/workflow/actions)
![Code size](https://img.shields.io/github/languages/code-size/chevere/workflow?style=flat-square)
[![Apache-2.0](https://img.shields.io/github/license/chevere/workflow?style=flat-square)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-blueviolet?style=flat-square)](https://phpstan.org/)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchevere%2Fworkflow%2F0.9)](https://dashboard.stryker-mutator.io/reports/github.com/chevere/workflow/0.9)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=alert_status)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=security_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=coverage)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![CodeFactor](https://www.codefactor.io/repository/github/chevere/workflow/badge)](https://www.codefactor.io/repository/github/chevere/workflow)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/9e33004e8791436f9e7e39093f3fd5e4)](https://app.codacy.com/gh/chevere/workflow/dashboard)

![Workflow](.github/banner/workflow-logo.svg)

## Resume

A Workflow is a configurable stored procedure that will run one or more jobs. Jobs are independent from each other, but interconnected as you can pass response references between jobs. Jobs supports conditional running based on variables and previous job responses.

## Quick start

Install with [Composer](https://packagist.org/packages/chevere/workflow).

```sh
composer require chevere/workflow
```

Workflow provides the following functions at the `Chevere\Workflow` namespace:

| Function   | Purpose                              |
| ---------- | ------------------------------------ |
| `workflow` | Create workflow made of named jobs   |
| `sync`     | Create synchronous blocking job      |
| `async`    | Create asynchronous non-blocking job |
| `variable` | Define workflow-level variable       |
| `response` | Define a job response reference      |

* A Job is defined by its [Action](https://chevere.org/library/action)
* Jobs are independent from each other, define shared variables using function `variable`
* Reference [job A response] -> [job B input] by using function `response`

## Hello, world

Run live example: `php demo/hello-world.php`

The basic example Workflow defines a greet for a given username. The job `greet` is a named argument and it takes the `GreetAction` plus its run [arguments](https://chevere.org/library/action.html#run).

```php
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\sync;

$workflow = workflow(
    greet: sync(
        new GreetAction(),
        username: variable('username'),
    ),
);
$variables = [
    'username' => $argv[1] ?? 'Walala',
];
$run = run($workflow, $variables);
echo $run->getReturn('greet')->string();
// Hello, Walala!
```

## Full example

Run live example: `php demo/image-resize.php`

For this example Workflow defines an image resize procedure in two sizes. All jobs are defined as async, but as there are dependencies between jobs (see `variable` and `response`) the system resolves a suitable run strategy.

```php
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\variable;

$workflow = workflow(
    thumb: async(
        new ImageResize(),
        file: variable('image'),
        fit: 'thumbnail',
    ),
    poster: async(
        new ImageResize(),
        file: variable('file'),
        fit: 'poster',
    ),
    storeThumb: async(
        new StoreFile(),
        file: response('thumb'),
        path: variable('savePath'),
    ),
    storePoster: async(
        new StoreFile(),
        file: response('poster'),
        path: variable('savePath'),
    )
);
```

The graph for the Workflow above shows that `thumb` and `poster` run async, just like `storeThumb` and `storePoster` but the storage jobs run after the first dependency level gets resolved.

```mermaid
  graph TD;
      thumb-->storeThumb;
      poster-->storePoster;
```

Use function `run` to run the Workflow, variables are passed as named arguments.

```php
use function Chevere\Workflow\run;

$run = run(
    $workflow,
    image: '/path/to/image-to-upload.png',
    savePath: '/path/to/storage/'
);

// Alternative syntax
$variables = [
    'image' => '/path/to/image-to-upload.png',
    'savePath' => '/path/to/storage/'
];
$run = run($workflow, ...$variables);
```

Use `getReturn` to retrieve a job response as a `CastArgument` object which can be used to get a typed response.

```php
$thumbFile = $run->getReturn('thumb')->string();
```

### Notes on async

Actions must support [serialization](https://www.php.net/manual/en/function.serialize.php) for being used on `async` jobs. For not serializable Actions as these interacting with connections (namely streams, database clients, etc.) you should use `sync` job.

## Documentation

Documentation is available at [chevere.org](https://chevere.org/packages/workflow).

## License

Copyright 2023 [Rodolfo Berrios A.](https://rodolfoberrios.com/)

This software is licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE) for the full license text.

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
