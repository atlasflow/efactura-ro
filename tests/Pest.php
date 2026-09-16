<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| The kernel is framework-free, so there is no base TestCase to bind. Two
| groups are reserved for tests that leave the machine: `anaf-public` posts
| fixture documents to ANAF's credential-free validator and runs by default
| in CI; `anaf-test` needs a real OAuth token for ANAF's test environment and
| is opt-in (`vendor/bin/pest --group=anaf-test`).
|
*/

function fixtureFile(string $path): string
{
    $file = __DIR__.'/fixtures/'.$path;

    if (! is_file($file)) {
        throw new RuntimeException("Fixture {$path} does not exist.");
    }

    return (string) file_get_contents($file);
}
