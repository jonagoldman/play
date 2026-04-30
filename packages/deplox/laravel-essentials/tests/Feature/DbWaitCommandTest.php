<?php

declare(strict_types=1);

use Deplox\Essentials\Database\Commands\DbWaitCommand;
use Illuminate\Console\Command;

test('exits SUCCESS when default connection is reachable', function (): void {
    $this->artisan(DbWaitCommand::class, ['--tries' => 1, '--delay' => 0])
        ->assertExitCode(Command::SUCCESS);
});

test('exits FAILURE when connection is unreachable after exhausting tries', function (): void {
    config(['database.connections.broken' => [
        'driver' => 'sqlite',
        'database' => '/nonexistent/path/that/cannot/exist.sqlite',
    ]]);

    $this->artisan(DbWaitCommand::class, [
        '--connection' => 'broken',
        '--tries' => 2,
        '--delay' => 0,
    ])->assertExitCode(Command::FAILURE);
});

test('signature exposes connection, tries, and delay options', function (): void {
    $command = new DbWaitCommand;

    expect($command->getName())->toBe('db:wait')
        ->and($command->getDefinition()->hasOption('connection'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('tries'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('delay'))->toBeTrue();
});
