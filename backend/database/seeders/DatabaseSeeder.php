<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            SubjectSeeder::class,
            PlanSeeder::class,
            UserSeeder::class,
            QuestionSeeder::class,
            DiscursiveAndEssayMockSeeder::class,
            SystemPromptSeeder::class,
            XavierPromptsSeeder::class,
        ]);
    }
}
