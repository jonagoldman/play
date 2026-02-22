<?php

declare(strict_types=1);

use Deplox\Shield\Policies\TokenPolicy;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->policy = new TokenPolicy;
    $this->user = User::factory()->create();
    $this->token = Token::factory()->for($this->user, 'owner')->create();
    $this->otherUser = User::factory()->create();
    $this->otherToken = Token::factory()->for($this->otherUser, 'owner')->create();
});

test('user can list their own tokens', function (): void {
    expect($this->policy->list($this->user, $this->user))->toBeTrue();
});

test('user cannot list another user tokens', function (): void {
    expect($this->policy->list($this->user, $this->otherUser))->toBeFalse();
});

test('user can view their own token', function (): void {
    expect($this->policy->view($this->user, $this->user, $this->token))->toBeTrue();
});

test('user cannot view another user token', function (): void {
    expect($this->policy->view($this->user, $this->otherUser, $this->otherToken))->toBeFalse();
});

test('user can create a token for themselves', function (): void {
    expect($this->policy->create($this->user, $this->user))->toBeTrue();
});

test('user cannot create a token for another user', function (): void {
    expect($this->policy->create($this->user, $this->otherUser))->toBeFalse();
});

test('user can delete their own token', function (): void {
    expect($this->policy->delete($this->user, $this->user, $this->token))->toBeTrue();
});

test('user cannot delete another user token', function (): void {
    expect($this->policy->delete($this->user, $this->otherUser, $this->otherToken))->toBeFalse();
});
