<?php

use App\Ai\EventDetails;
use App\Ai\EventDetailsExtractor;
use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);
});

function hashEvent(string $name, ?string $description = 'Hares: Fletch. Start at Otto\'s 3 pm.'): Event
{
    return Event::create([
        'name' => $name,
        'description' => $description ?? '',
        'location' => 'State College, PA',
        'starts_at' => now()->subYear(),
        'ends_at' => now()->subYear()->addDay(),
        'base_price' => 0,
        'event_tag' => str($name)->slug()->upper()->limit(12, ''),
        'private' => false,
        'created_by' => test()->admin->id,
    ]);
}

function details(array $values = []): EventDetails
{
    $details = new EventDetails;
    foreach ($values as $key => $value) {
        $details->{$key} = $value;
    }

    return $details;
}

function fakeExtractor(?EventDetails $details): void
{
    test()->instance(EventDetailsExtractor::class, new class($details) extends EventDetailsExtractor
    {
        public function __construct(private ?EventDetails $details) {}

        public function extract(string $description, ?string $location = null): ?EventDetails
        {
            return $this->details;
        }
    });
}

test('stores extracted trail details in the event properties', function () {
    $event = hashEvent('NVHHH 1900');

    fakeExtractor(details([
        'hares' => ['Fletch'],
        'start_location' => "Otto's",
        'hash_cash' => '$5',
        'directions' => 'Park in the rear lot off Houserville Rd.',
    ]));

    $this->artisan('app:extract-event-details')->assertSuccessful();

    expect($event->fresh()->properties['trail'])->toBe([
        'hares' => ['Fletch'],
        'start_location' => "Otto's",
        'hash_cash' => '$5',
        'directions' => 'Park in the rear lot off Houserville Rd.',
    ]);
});

test('keeps existing properties when adding trail details', function () {
    $event = hashEvent('NVHHH 1901');
    $event->properties = ['fields' => [['name' => 'shirt', 'type' => 'text']]];
    $event->save();

    fakeExtractor(details(['hares' => ['Tinsel']]));

    $this->artisan('app:extract-event-details')->assertSuccessful();

    expect($event->fresh()->properties['fields'])->toHaveCount(1)
        ->and($event->fresh()->properties['trail']['hares'])->toBe(['Tinsel']);
});

test('skips events without a description', function () {
    $event = hashEvent('NVHHH 1902', description: null);

    fakeExtractor(details(['hares' => ['Nobody']]));

    $this->artisan('app:extract-event-details')->assertSuccessful();

    expect($event->fresh()->properties)->toBeNull();
});

test('skips events that already have trail details unless forced', function () {
    $event = hashEvent('NVHHH 1903');
    $event->properties = ['trail' => ['hares' => ['Old Hare']]];
    $event->save();

    fakeExtractor(details(['hares' => ['New Hare']]));

    $this->artisan('app:extract-event-details')->assertSuccessful();
    expect($event->fresh()->properties['trail']['hares'])->toBe(['Old Hare']);

    $this->artisan('app:extract-event-details', ['--force' => true])->assertSuccessful();
    expect($event->fresh()->properties['trail']['hares'])->toBe(['New Hare']);
});

test('a failed extraction leaves the event untouched', function () {
    $event = hashEvent('NVHHH 1904');

    fakeExtractor(null);

    $this->artisan('app:extract-event-details')->assertSuccessful();

    expect($event->fresh()->properties)->toBeNull();
});

test('the event option scopes the run', function () {
    $wanted = hashEvent('NVHHH 1905');
    $other = hashEvent('NVHHH 1906');

    fakeExtractor(details(['hares' => ['Scoped']]));

    $this->artisan('app:extract-event-details', ['--event' => $wanted->slug])->assertSuccessful();

    expect($wanted->fresh()->properties['trail']['hares'])->toBe(['Scoped'])
        ->and($other->fresh()->properties)->toBeNull();
});

test('blank descriptions never reach the llm', function () {
    config(['services.llm.api_key' => null]);

    expect((new EventDetailsExtractor)->extract('  '))->toBeNull();
});

test('a missing api key fails loudly instead of silently storing nothing', function () {
    config(['services.llm.api_key' => null]);

    (new EventDetailsExtractor)->extract('Hares: Fletch');
})->throws(RuntimeException::class, 'No LLM API key set');
