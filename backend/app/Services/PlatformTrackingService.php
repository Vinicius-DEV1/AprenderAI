<?php

namespace App\Services;

use App\Models\PlatformEvent;
use App\Models\PlatformHeartbeat;
use App\Models\PlatformSession;
use Illuminate\Support\Str;

class PlatformTrackingService
{
    /**
     * Start a new session for the user.
     * Returns the generated session_token.
     */
    public function startSession(int $userId, string $ipAddress, string $userAgent): string
    {
        $token = Str::random(64);

        PlatformSession::create([
            'user_id'             => $userId,
            'session_token'       => $token,
            'ip_address'          => $ipAddress,
            'user_agent'          => $userAgent,
            'started_at'          => now(),
            'last_heartbeat_at'   => now(),
        ]);

        return $token;
    }

    /**
     * End an active session.
     */
    public function endSession(string $sessionToken): void
    {
        PlatformSession::where('session_token', $sessionToken)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    /**
     * Upsert heartbeat (one row per user) and update last_heartbeat_at on the session.
     */
    public function heartbeat(int $userId, string $sessionToken, ?string $currentPage): void
    {
        $now = now();

        // Upsert — only one heartbeat row per user
        PlatformHeartbeat::updateOrCreate(
            ['user_id' => $userId],
            [
                'session_token' => $sessionToken,
                'current_page'  => $currentPage,
                'pinged_at'     => $now,
            ]
        );

        // Also keep the session row fresh
        PlatformSession::where('session_token', $sessionToken)
            ->whereNull('ended_at')
            ->update(['last_heartbeat_at' => $now]);
    }

    /**
     * Record a platform event.
     */
    public function recordEvent(
        int     $userId,
        string  $sessionToken,
        string  $eventType,
        ?string $page = null,
        ?int    $resourceId = null,
        ?string $resourceType = null,
        ?int    $durationSeconds = null,
        ?array  $metadata = null
    ): void {
        PlatformEvent::create([
            'user_id'          => $userId,
            'session_token'    => $sessionToken,
            'event_type'       => $eventType,
            'page'             => $page,
            'resource_id'      => $resourceId,
            'resource_type'    => $resourceType,
            'duration_seconds' => $durationSeconds,
            'metadata'         => $metadata,
        ]);
    }
}
