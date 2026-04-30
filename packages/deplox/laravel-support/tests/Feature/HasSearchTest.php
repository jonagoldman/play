<?php

declare(strict_types=1);

use Deplox\Support\Database\Eloquent\Concerns\HasSearch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

final class SearchablePost extends Model
{
    use HasSearch;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'searchable_posts';

    protected $guarded = [];

    /** @var list<string> */
    protected $searchable = ['title', 'body'];
}

beforeEach(function (): void {
    Schema::create('searchable_posts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->string('body')->nullable();
    });
});

test('whereSearch matches across allowlisted columns', function (): void {
    SearchablePost::create(['title' => 'Hello World', 'body' => 'lorem']);
    SearchablePost::create(['title' => 'Goodbye', 'body' => 'mentioning hello inside']);
    SearchablePost::create(['title' => 'Unrelated', 'body' => 'nothing']);

    Request::replace(['search' => 'hello']);

    expect(SearchablePost::query()->whereSearch(allowed: ['title', 'body'])->count())->toBe(2);
});

test('whereSearch silently drops columns not in the model allowlist', function (): void {
    SearchablePost::create(['title' => 'visible', 'body' => 'attack-vector text']);

    Request::replace(['search' => 'attack-vector']);

    expect(SearchablePost::query()->whereSearch(allowed: ['title'])->count())->toBe(0);
});

test('whereSearch is a no-op when search param is empty or missing', function (): void {
    SearchablePost::create(['title' => 'a']);
    SearchablePost::create(['title' => 'b']);

    Request::replace([]);

    expect(SearchablePost::query()->whereSearch(allowed: ['title'])->count())->toBe(2);

    Request::replace(['search' => '   ']);

    expect(SearchablePost::query()->whereSearch(allowed: ['title'])->count())->toBe(2);
});

test('whereSearch is a no-op when allowed list is empty', function (): void {
    SearchablePost::create(['title' => 'visible']);

    Request::replace(['search' => 'visible']);

    expect(SearchablePost::query()->whereSearch(allowed: [])->count())->toBe(1);
});

test('whereSearch supports a custom query parameter name', function (): void {
    SearchablePost::create(['title' => 'apple']);
    SearchablePost::create(['title' => 'banana']);

    Request::replace(['q' => 'apple']);

    expect(SearchablePost::query()->whereSearch(allowed: ['title'], param: 'q')->count())->toBe(1);
});

test('getSearchable returns the configured columns', function (): void {
    expect((new SearchablePost)->getSearchable())->toBe(['title', 'body']);
});
