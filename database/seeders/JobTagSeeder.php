<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tag::insert([
            [
                'name' => 'Weekend Jobs',
                'slug' => 'weekend-jobs',
                'has_landing_page' => true,
                'impressions' => 164000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Night Shift Jobs',
                'slug' => 'night-shift-jobs',
                'has_landing_page' => true,
                'impressions' => 49000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Summer Jobs',
                'slug' => 'summer-jobs',
                'has_landing_page' => true,
                'impressions' => 12500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
