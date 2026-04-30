<?php

declare(strict_types=1);

use Deplox\Support\Database\Eloquent\Concerns\HasSorting;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

final class SortablePost extends Model
{
    use HasSorting;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'sortable_posts';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::create('sortable_posts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->integer('rank');
    });
});

test('withSorting orders ascending by default', function (): void {
    SortablePost::create(['title' => 'b', 'rank' => 2]);
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace(['sort' => 'rank']);

    expect(SortablePost::query()->withSorting(['rank'])->pluck('title')->all())->toBe(['a', 'b']);
});

test('withSorting orders descending with - prefix', function (): void {
    SortablePost::create(['title' => 'b', 'rank' => 2]);
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace(['sort' => '-rank']);

    expect(SortablePost::query()->withSorting(['rank'])->pluck('title')->all())->toBe(['b', 'a']);
});

test('withSorting supports + prefix as ascending', function (): void {
    SortablePost::create(['title' => 'b', 'rank' => 2]);
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace(['sort' => '+rank']);

    expect(SortablePost::query()->withSorting(['rank'])->pluck('title')->all())->toBe(['a', 'b']);
});

test('withSorting drops columns not in the allowlist', function (): void {
    SortablePost::create(['title' => 'b', 'rank' => 1]);
    SortablePost::create(['title' => 'a', 'rank' => 2]);

    Request::replace(['sort' => 'title']);

    expect(SortablePost::query()->withSorting(['rank'])->pluck('title')->all())->toBe(['b', 'a']);
});

test('withSorting accepts multi-column sort with mixed directions', function (): void {
    SortablePost::create(['title' => 'a', 'rank' => 2]);
    SortablePost::create(['title' => 'b', 'rank' => 1]);
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace(['sort' => 'title,-rank']);

    expect(SortablePost::query()->withSorting(['title', 'rank'])->pluck('rank')->all())->toBe([2, 1, 1]);
});

test('withSorting is a no-op when sort param is missing', function (): void {
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace([]);

    expect(SortablePost::query()->withSorting(['rank'])->count())->toBe(1);
});

test('withSorting supports a custom query parameter name', function (): void {
    SortablePost::create(['title' => 'b', 'rank' => 2]);
    SortablePost::create(['title' => 'a', 'rank' => 1]);

    Request::replace(['order' => '-rank']);

    expect(SortablePost::query()->withSorting(['rank'], param: 'order')->pluck('title')->all())->toBe(['b', 'a']);
});
