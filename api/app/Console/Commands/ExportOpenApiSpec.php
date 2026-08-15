<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * CLAUDE.md task: "Add an artisan command that writes the spec to
 * docs/openapi.json." Thin wrapper around Scramble's generator (rather
 * than `scramble:export --path=...`) so the committed path is fixed in one
 * place instead of relied on being typed correctly on every run, and so
 * the CI staleness check (`--check`) can live next to it.
 */
class ExportOpenApiSpec extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:export-openapi
        {--check : Fail instead of writing, if the committed spec is stale}';

    /**
     * @var string
     */
    protected $description = 'Export the OpenAPI spec to docs/openapi.json (or verify it is up to date)';

    public function handle(Generator $generator): int
    {
        $path = dirname(base_path()).'/docs/openapi.json';
        $config = Scramble::getGeneratorConfig('default');
        $json = json_encode($generator($config), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if ($this->option('check')) {
            if (! File::exists($path)) {
                $this->error('docs/openapi.json does not exist. Run `php artisan app:export-openapi` and commit it.');

                return self::FAILURE;
            }

            if (File::get($path) !== $json) {
                $this->error('docs/openapi.json is stale. Run `php artisan app:export-openapi` and commit the result.');

                return self::FAILURE;
            }

            $this->info('docs/openapi.json is up to date.');

            return self::SUCCESS;
        }

        File::put($path, $json);
        $this->info('Exported OpenAPI spec to docs/openapi.json.');

        return self::SUCCESS;
    }
}
