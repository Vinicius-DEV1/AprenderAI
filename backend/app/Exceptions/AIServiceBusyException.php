<?php

namespace App\Exceptions;

use Exception;

/**
 * AIServiceBusyException
 * 
 * Thrown when all available AI API keys are currently locked by other workers
 * or blacklisted due to quota limits. This signals the Job to release itself
 * back to the queue for a retry later.
 */
class AIServiceBusyException extends Exception
{
    protected $message = 'All AI API keys are currently busy or unavailable.';
}
