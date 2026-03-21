<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PruneFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:failed:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hard clears all failed jobs from the database and Redis.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting full prune of failed jobs...');

        // 1. Flush Redis queues if any failed states linger there
        Artisan::call('queue:forget');
        
        // 2. Hard truncate the failed_jobs table (much faster than iterative delete)
        DB::table('failed_jobs')->truncate();

        $this->info('Successfully pruned all failed jobs. System is clean.');
        return 0;
    }
}
