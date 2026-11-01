<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Kennel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Kennel::updateOrCreate(
            ['abbreviation' => 'NVHHH'],
            ['full_name' => 'Nittany Valley Hash House Harriers'],
        );

        Event::create([
            'name' => 'NVHHH #1900: NITTANY CALLING',
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-01 14:00:00', 'America/New_York'),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-03 12:00:00', 'America/New_York'),
            'location' => 'Sons of Italy Campground\n44 Sons Rd, Lock Haven, PA 17745',
            'kennel' => 'NVHHH',
            'description' => 'markdown goes here',
            'created_by' => 22,
            'event_tag' => 'NVHHH1900',
            'base_price' => 16500,
        ]);
    }
}
