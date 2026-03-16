<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformTrackingService;
use Illuminate\Http\Request;

/**
 * PlatformTrackingController
 *
 * Receives native tracking events from the frontend.
 * All endpoints require authentication (sanctum).
 * These are fire-and-forget: always return 204 No Content to avoid
 * blocking the user's browser with unnecessary response handling.
 */
class PlatformTrackingController extends Controller
{
    public function __construct(protected PlatformTrackingService $tracker)
    {
    }

    /**
     * POST /api/v1/platform/session/start
     * Called immediately after login to open a new session.
     */
    public function sessionStart(Request $request)
    {
        $token = $this->tracker->startSession(
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['session_token' => $token]);
    }

    /**
     * POST /api/v1/platform/session/end
     * Called on logout or beforeunload.
     */
    public function sessionEnd(Request $request)
    {
        $request->validate(['session_token' => 'required|string|max:64']);

        $this->tracker->endSession($request->input('session_token'));

        return response()->noContent();
    }

    /**
     * POST /api/v1/platform/heartbeat
     * Called every 60 seconds while the user is active.
     */
    public function heartbeat(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string|max:64',
            'current_page'  => 'nullable|string|max:255',
        ]);

        $this->tracker->heartbeat(
            $request->user()->id,
            $request->input('session_token'),
            $request->input('current_page')
        );

        return response()->noContent();
    }

    /**
     * POST /api/v1/platform/event
     * Records any user action event.
     */
    public function event(Request $request)
    {
        $request->validate([
            'session_token'    => 'required|string|max:64',
            'event_type'       => 'required|string|max:80',
            'page'             => 'nullable|string|max:255',
            'resource_id'      => 'nullable|integer',
            'resource_type'    => 'nullable|string|max:50',
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata'         => 'nullable|array',
        ]);

        $this->tracker->recordEvent(
            userId:          $request->user()->id,
            sessionToken:    $request->input('session_token'),
            eventType:       $request->input('event_type'),
            page:            $request->input('page'),
            resourceId:      $request->input('resource_id'),
            resourceType:    $request->input('resource_type'),
            durationSeconds: $request->input('duration_seconds'),
            metadata:        $request->input('metadata'),
        );

        return response()->noContent();
    }
}
