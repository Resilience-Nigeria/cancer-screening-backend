<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Biopsy', 'slug' => 'biopsy', 'category' => 'diagnostic'],
            ['name' => 'Mammography', 'slug' => 'mammography', 'category' => 'imaging'],
            ['name' => 'Colposcopy', 'slug' => 'colposcopy', 'category' => 'diagnostic'],
            ['name' => 'Ultrasound (Breast/Liver)', 'slug' => 'ultrasound', 'category' => 'imaging'],
            ['name' => 'PSA Test', 'slug' => 'psa-test', 'category' => 'lab'],
        ];

        foreach ($services as $s) {
            Service::updateOrCreate(['slug' => $s['slug']], $s);
        }
    }
}