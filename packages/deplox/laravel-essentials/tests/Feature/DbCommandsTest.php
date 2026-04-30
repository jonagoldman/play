<?php

declare(strict_types=1);

use Deplox\Essentials\Database\Commands\DbDropCommand;
use Deplox\Essentials\Database\Commands\DbMakeCommand;
use Illuminate\Console\Command;

afterEach(function (): void {
    DbDropCommand::prohibit(false);
    DbMakeCommand::prohibit(false);
});

test('db:make is registered with the expected signature', function (): void {
    $command = new DbMakeCommand;

    expect($command->getName())->toBe('db:make')
        ->and($command->getDescription())->toBe('Create new database')
        ->and($command->getDefinition()->hasArgument('name'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('force'))->toBeTrue();
});

test('db:drop is registered with the expected signature', function (): void {
    $command = new DbDropCommand;

    expect($command->getName())->toBe('db:drop')
        ->and($command->getDescription())->toBe('Delete existing database')
        ->and($command->getDefinition()->hasArgument('name'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('force'))->toBeTrue();
});

test('db:make returns FAILURE when prohibited', function (): void {
    DbMakeCommand::prohibit();

    $this->artisan(DbMakeCommand::class, ['name' => 'x', '--force' => true])
        ->assertExitCode(Command::FAILURE);
});

test('db:drop returns FAILURE when prohibited', function (): void {
    DbDropCommand::prohibit();

    $this->artisan(DbDropCommand::class, ['name' => 'x', '--force' => true])
        ->assertExitCode(Command::FAILURE);
});

test('db:make and db:drop both implement Isolatable', function (): void {
    expect(new DbMakeCommand)->toBeInstanceOf(Illuminate\Contracts\Console\Isolatable::class)
        ->and(new DbDropCommand)->toBeInstanceOf(Illuminate\Contracts\Console\Isolatable::class);
});
