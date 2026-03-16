import axios from './axios';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface PlatformEventPayload {
  session_token: string;
  event_type: string;
  page?: string;
  resource_id?: number;
  resource_type?: string;
  duration_seconds?: number;
  metadata?: Record<string, unknown>;
}

// ─── API Calls ────────────────────────────────────────────────────────────────

/**
 * Start a new platform session.
 * Returns the session_token to be stored in memory for the lifetime of the session.
 */
export async function startPlatformSession(): Promise<string | null> {
  try {
    const res = await axios.post('/api/v1/platform/session/start');
    return res.data?.session_token ?? null;
  } catch {
    return null;
  }
}

/**
 * End the current platform session (called on logout or page unload).
 */
export async function endPlatformSession(sessionToken: string): Promise<void> {
  try {
    await axios.post('/api/v1/platform/session/end', { session_token: sessionToken });
  } catch {
    // Fire-and-forget — silent fail is acceptable
  }
}

/**
 * Send a heartbeat ping to keep the session alive and update "current page".
 */
export async function sendPlatformHeartbeat(
  sessionToken: string,
  currentPage: string
): Promise<void> {
  try {
    await axios.post('/api/v1/platform/heartbeat', {
      session_token: sessionToken,
      current_page: currentPage,
    });
  } catch {
    // Fire-and-forget
  }
}

/**
 * Track a platform event (question answered, simulation created, etc.)
 */
export async function trackPlatformEvent(payload: PlatformEventPayload): Promise<void> {
  try {
    await axios.post('/api/v1/platform/event', payload);
  } catch {
    // Fire-and-forget
  }
}
