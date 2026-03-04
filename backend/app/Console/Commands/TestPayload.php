<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class TestPayload extends Command
{
    protected $signature = 'test:payload';
    protected $description = 'Test API Payload';

    public function handle()
    {
        $user = User::where('email', 'admin@aprenderai.com')->first();
        auth()->login($user);

        $dashRes = app(\App\Http\Controllers\Api\DashboardController::class)->index(request());
        $this->info("--- DASHBOARD PAYLOAD ---");
        $this->line(json_encode($dashRes->getData(), JSON_PRETTY_PRINT));
        $this->info("-------------------------");

        try {
            $planRes = app(\App\Http\Controllers\Api\V1\StudyPlanController::class)->show(request());
            $this->info("--- STUDY PLAN PAYLOAD ---");
            $this->line(json_encode(['status' => 200, 'body' => $planRes->getData()], JSON_PRETTY_PRINT));
            $this->info("-------------------------");
        } catch (\Exception $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $this->info("--- STUDY PLAN PAYLOAD ---");
            $this->line(json_encode(['status' => $status, 'body' => ['message' => $e->getMessage(), 'code' => $e->getCode()]], JSON_PRETTY_PRINT));
            $this->info("-------------------------");
        }
    }
}
