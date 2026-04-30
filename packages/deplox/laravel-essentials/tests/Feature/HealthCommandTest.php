<?php

declare(strict_types=1);

use Deplox\Essentials\Console\HealthCommand;
use Illuminate\Console\Command;

test('exits SUCCESS when database, cache, and queue are healthy', function (): void {
    $this->artisan(HealthCommand::class)->assertExitCode(Command::SUCCESS);
});

test('signature exposes the expected command name', function (): void {
    $command = new HealthCommand;

    expect($command->getName())->toBe('health')
        ->and($command->getDescription())->toBe('Composite health check (database, cache, queue).');
});
