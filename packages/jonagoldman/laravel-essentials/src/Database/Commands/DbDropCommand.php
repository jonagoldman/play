<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait as Confirmable;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Console\Isolatable;
use JonaGoldman\Essentials\Database\Actions\DeleteDatabase;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'db:drop',
    description: 'Delete existing database',
)]
final class DbDropCommand extends Command implements Isolatable
{
    use Confirmable;
    use Prohibitable;

    protected $signature = 'db:drop {name : The database name}
                {--force : Force the operation to run when in production}';

    /**
     * Execute the console command.
     */
    public function handle(DeleteDatabase $deleteDatabase): int
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        $deleteDatabase($this->argument('name'));

        $this->components->info('Database dropped successfully.');

        return self::SUCCESS;
    }
}
