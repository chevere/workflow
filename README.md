# Workflow

> 🔔 Subscribe to the [newsletter](https://chv.to/chevere-newsletter) to don't miss any update regarding Chevere.

![Chevere](chevere.svg)

[![Build](https://img.shields.io/github/actions/workflow/status/chevere/workflow/test.yml?branch=0.8&style=flat-square)](https://github.com/chevere/workflow/actions)
![Code size](https://img.shields.io/github/languages/code-size/chevere/workflow?style=flat-square)
[![Apache-2.0](https://img.shields.io/github/license/chevere/workflow?style=flat-square)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-blueviolet?style=flat-square)](https://phpstan.org/)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchevere%2Fworkflow%2F0.8)](https://dashboard.stryker-mutator.io/reports/github.com/chevere/workflow/0.8)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=alert_status)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=security_rating)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=coverage)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=chevere_workflow&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chevere_workflow)
[![CodeFactor](https://www.codefactor.io/repository/github/chevere/workflow/badge)](https://www.codefactor.io/repository/github/chevere/workflow)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/9e33004e8791436f9e7e39093f3fd5e4)](https://www.codacy.com/gh/chevere/workflow/dashboard)

![Workflow](.github/banner/workflow-logo.svg)

## Quick start

Install with [Composer](https://getcomposer.org).

```sh
composer install chevere/workflow
```

Create a Workflow by passing named jobs.

* `async` for asynchronous non-blocking jobs
* `sync` for synchronous blocking jobs
* `variable` for defining a variable
* `reference` to define a reference to a previous job

```php
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\async;
use function Chevere\Workflow\variable;

$workflow = workflow(
    thumb: async(
        ImageResize::class,
        file: variable('file'),
        fit: 'thumbnail',
    ),
    poster: async(
        ImageResize::class,
        file: variable('file'),
        fit: 'poster',
    ),
    store: sync(
        StoreFiles::class,
        reference('thumb', 'out'),
        reference('poster', 'out'),
    )
);
```

Run your Workflow:

```php
use function Chevere\Workflow\run;

$variables = [
    'file' => '/path/to/file'
];
$run = run($workflow, $variables);
```

Variable `$run` will be assigned to an object implementing `RunInterface`, which you can query for obtaining data from the Workflow runtime.

## Documentation

Documentation is available at [chevere.org](https://chevere.org/packages/workflow).

## License

Copyright 2023 [Rodolfo Berrios A.](https://rodolfoberrios.com/)

This software is licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE) for the full license text.

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
