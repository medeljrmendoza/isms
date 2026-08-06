<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * No-op: the app has no local database. Every model reads/writes the
     * live legacy connection directly (see individual seeders, which are
     * now dead code kept only for reference — nothing here runs them).
     */
    public function run(): void {}
}
