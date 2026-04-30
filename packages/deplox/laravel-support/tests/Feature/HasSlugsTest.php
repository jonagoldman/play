<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\CustomSluggable;
use Deplox\Support\Tests\Fixtures\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('posts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->ulid('author_id')->nullable();
        $table->timestamp('expires_at')->nullable();
    });
});

test('slug is generated from name on create', function (): void {
    $post = Post::create(['name' => 'Hello World']);

    expect($post->slug)->toBe('hello-world');
});

test('slug regenerates on save when source changes', function (): void {
    $post = Post::create(['name' => 'Original Title']);

    $post->name = 'Updated Title';
    $post->save();

    expect($post->fresh()->slug)->toBe('updated-title');
});

test('slugify lowercases, trims, and replaces non-alphanum with dashes', function (): void {
    expect(Post::slugify('  Hello, World! 123  '))->toBe('hello-world-123');
});

test('getSluggable returns the configured map', function (): void {
    expect((new Post)->getSluggable())->toBe(['name' => 'slug']);
});

test('setSluggableValues respects custom sluggable map declared on the model', function (): void {
    $model = new CustomSluggable;
    $model->title = 'My Title';
    $model->subtitle = 'My Subtitle';
    $model->setSluggableValues();

    expect($model->permalink)->toBe('my-title')
        ->and($model->sub_slug)->toBe('my-subtitle');
});
