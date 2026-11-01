<?php

namespace App\Ai;

use Cognesy\Schema\Attributes\Description;

#[Description('Facts parsed out of a hash house harriers event write-up. Null when the text does not say.')]
final class EventDetails
{
    /** @var list<string> */
    #[Description('Hare names exactly as written, one entry per hare (e.g. "Fletch", "Sux2Blow", "Radar Boy"). Empty when no hare is named.')]
    public array $hares = [];

    #[Description('Where the trail starts, as written (e.g. "Otto\'s", "Spring Creek Park parking lot"). Do not invent an address.')]
    public ?string $start_location = null;

    #[Description('Cost as written: a cash amount ("$5", "$7 hash cash"), "pay as you go", "BYOB", "no hash fee". Null when not mentioned.')]
    public ?string $hash_cash = null;

    #[Description('Directions or address detail from the write-up: street address, which lot to park in, landmarks, how to find the start. Null when the text adds nothing beyond the venue name.')]
    public ?string $directions = null;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
