<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\Event;

class EventSeeder extends Seeder
{
    public function run()
    {
        Event::create([
            'name' => 'NVHHH #1900: NITTANY CALLING',
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-01 14:00:00', 'America/New_York'),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-03 12:00:00', 'America/New_York'),
            'location' => 'Sons of Italy Campground\n44 Sons Rd, Lock Haven, PA 17745',
            'kennel' => 'Nittany Valley Hash House Harriers',
            'description' => 'markdown goes here',
            'created_by' => 22,
        ]);
    }
}
