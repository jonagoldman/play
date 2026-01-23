<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait as Confirmable;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Console\Isolatable;
use JonaGoldman\Essentials\Database\Actions\CreateDatabase;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'db:make',
    description: 'Create new database',
)]
final class DbMakeCommand extends Command implements Isolatable
{
    use Confirmable;
    use Prohibitable;

    protected $signature = 'db:make {name : The database name}
                {--force : Force the operation to run when in production}';

    /**
     * Execute the console command.
     */
    public function handle(CreateDatabase $createDatabase): int
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        $createDatabase($this->argument('name'));

        $this->components->info('Database created successfully.');

        return self::SUCCESS;
    }
}
