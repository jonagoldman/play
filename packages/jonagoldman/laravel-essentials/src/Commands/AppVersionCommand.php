<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:version',
    description: 'Display the current application scaffold version',
)]
final class AppVersionCommand extends Command
{
    public const string VERSION = '12.7.1';

    public function handle(): int
    {
        $this->info(self::VERSION);

        return self::SUCCESS;
    }
}
