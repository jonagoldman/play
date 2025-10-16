<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Commands;

use Illuminate\Console\Command;

final class AppVersionCommand extends Command
{
    public const string VERSION = '12.7.1';

    public $signature = 'app:version';

    public $description = 'Display the current application scaffold version';

    public function handle(): int
    {
        $this->info(self::VERSION);

        return self::SUCCESS;
    }
}
