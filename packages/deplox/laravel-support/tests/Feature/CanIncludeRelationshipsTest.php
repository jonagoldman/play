<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Author;
use Deplox\Support\Tests\Fixtures\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('authors', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
    });

    Schema::create('posts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->ulid('author_id')->nullable();
        $table->timestamp('expires_at')->nullable();
    });
});

test('withIncluded eager loads relationships from include query param', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);

    Request::replace(['include' => 'author']);

    $posts = Post::query()->withIncluded(allowed: ['author'])->get();

    expect($posts->first()->relationLoaded('author'))->toBeTrue();
});

test('withIncluded silently drops relations not in allowlist', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);

    Request::replace(['include' => 'author']);

    $posts = Post::query()->withIncluded(allowed: ['comments'])->get();

    expect($posts->first()->relationLoaded('author'))->toBeFalse();
});

test('withIncluded counts relationships from with_count query param', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);
    Post::create(['name' => 'B', 'author_id' => $author->id]);

    Request::replace(['with_count' => 'posts']);

    $authors = Author::query()->withIncluded(allowedCounts: ['posts'])->get();

    expect($authors->first()->posts_count)->toBe(2);
});

test('loadIncluded lazy loads onto already-resolved models', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);

    Request::replace(['include' => 'author']);

    $post = Post::query()->first()->loadIncluded(allowed: ['author']);

    expect($post->relationLoaded('author'))->toBeTrue()
        ->and($post->author->name)->toBe('Ada');
});

test('parseIncluded handles whitespace and empty values', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);

    Request::replace(['include' => '  author  , unknown , ']);

    $post = Post::query()->withIncluded(allowed: ['author'])->first();

    expect($post->relationLoaded('author'))->toBeTrue();
});

test('parseIncluded returns empty array when query param missing', function (): void {
    $author = Author::create(['name' => 'Ada']);
    Post::create(['name' => 'A', 'author_id' => $author->id]);

    Request::replace([]);

    $post = Post::query()->withIncluded(allowed: ['author'])->first();

    expect($post->relationLoaded('author'))->toBeFalse();
});
