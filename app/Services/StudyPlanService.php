<?php

namespace App\Services;

class StudyPlanService
{
    public function __construct()
    {
        throw new \Exception("StudyPlanService is deprecated. Use App\Services\Study\StudyStatsService, StudyDashboardService, or StudyPlanGenerator instead.");
    }
}