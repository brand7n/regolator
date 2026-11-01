<?php

use App\Models\Event;
use App\Models\Kennel;
use App\Models\User;

beforeEach(function () {
    $this->host = User::create([
        'name' => 'Host',
        'email' => 'host@example.com',
        'password' => 'secret',
    ]);
});

function makeEvent(string $kennel): Event
{
    return Event::create([
        'name' => 'Trail '.$kennel,
        'kennel' => $kennel,
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subDays(6),
        'base_price' => 0,
        'private' => false,
        'created_by' => test()->host->id,
    ]);
}

test('a freeform kennel string ties the event to a canonical kennel', function () {
    $first = makeEvent('NVHHH');
    $second = makeEvent('  nvhhh ');

    expect($first->kennel_id)->not->toBeNull()
        ->and($second->kennel_id)->toBe($first->kennel_id)
        ->and(Kennel::count())->toBe(1)
        ->and($first->kennel->abbreviation)->toBe('NVHHH');
});

test('different kennel names stay separate', function () {
    $a = makeEvent('Nittany Valley Hash House Harriers');
    $b = makeEvent('Nittany Valley H3');

    expect($a->kennel_id)->not->toBe($b->kennel_id)
        ->and(Kennel::count())->toBe(2);
});

test('resolve reuses an existing kennel and creates missing ones', function () {
    $kennel = Kennel::resolve('BFH3');

    expect(Kennel::resolve('bfh3')?->id)->toBe($kennel->id)
        ->and(Kennel::count())->toBe(1);
});

test('the full name and the abbreviation resolve to the same kennel', function () {
    $kennel = Kennel::create([
        'abbreviation' => 'NVHHH',
        'full_name' => 'Nittany Valley Hash House Harriers',
    ]);

    expect(Kennel::resolve('NVHHH')?->id)->toBe($kennel->id)
        ->and(Kennel::resolve('nittany valley hash house harriers')?->id)->toBe($kennel->id)
        ->and(Kennel::count())->toBe(1);
});

test('a kennel is labelled by its full name, falling back to the abbreviation', function () {
    expect(Kennel::create(['full_name' => 'Nittany Valley Hash House Harriers', 'abbreviation' => 'NVHHH'])->label())
        ->toBe('Nittany Valley Hash House Harriers')
        ->and(Kennel::create(['abbreviation' => 'BFH3'])->label())->toBe('BFH3');
});

test('blank kennel names resolve to nothing', function () {
    expect(Kennel::resolve('   '))->toBeNull()
        ->and(Kennel::resolve(null))->toBeNull()
        ->and(makeEvent('')->kennel_id)->toBeNull();
});
