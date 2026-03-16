import { useEffect, useRef, useCallback } from 'react';
import { useLocation } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import {
  startPlatformSession,
  endPlatformSession,
  sendPlatformHeartbeat,
  trackPlatformEvent,
  type PlatformEventPayload,
} from '../api/platformTracking';

const HEARTBEAT_INTERVAL_MS = 60_000; // 60 seconds

/**
 * usePlatformTracking
 *
 * Manages the entire native platform tracking lifecycle for authenticated users:
 *  - Opens a session on mount (after user is present)
 *  - Sends heartbeats every 60 seconds
 *  - Updates current page on route changes
 *  - Closes session on logout or beforeunload
 *  - Exposes `trackEvent()` for components to fire events
 */
export function usePlatformTracking() {
  const { user } = useAuthStore();
  const location = useLocation();

  const sessionTokenRef = useRef<string | null>(null);
  const heartbeatTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ----- Session Start -----
  useEffect(() => {
    if (!user) return;

    let mounted = true;

    const init = async () => {
      const token = await startPlatformSession();
      if (token && mounted) {
        sessionTokenRef.current = token;
      }
    };

    init();

    return () => {
      mounted = false;
    };
  // Only run once when the user logs in (user.id changes)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id]);

  // ----- Heartbeat -----
  useEffect(() => {
    if (!user) return;

    const beat = () => {
      if (sessionTokenRef.current) {
        sendPlatformHeartbeat(sessionTokenRef.current, location.pathname);
      }
    };

    // Send an immediate heartbeat
    beat();

    heartbeatTimerRef.current = setInterval(beat, HEARTBEAT_INTERVAL_MS);

    return () => {
      if (heartbeatTimerRef.current) {
        clearInterval(heartbeatTimerRef.current);
      }
    };
  // Re-establish interval if user changes (e.g. admin impersonation)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id]);

  // ----- Update current page on route change -----
  useEffect(() => {
    if (!user || !sessionTokenRef.current) return;
    sendPlatformHeartbeat(sessionTokenRef.current, location.pathname);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname]);

  // ----- Session End on page unload -----
  useEffect(() => {
    const handleUnload = () => {
      if (sessionTokenRef.current) {
        // Use sendBeacon so the request survives page close
        const payload = JSON.stringify({ session_token: sessionTokenRef.current });
        navigator.sendBeacon('/api/v1/platform/session/end', payload);
      }
    };

    window.addEventListener('beforeunload', handleUnload);
    return () => window.removeEventListener('beforeunload', handleUnload);
  }, []);

  // ----- Session End on logout (user becomes null) -----
  const prevUserIdRef = useRef<number | undefined>(undefined);
  useEffect(() => {
    const prevId = prevUserIdRef.current;
    const currId = user?.id;

    if (prevId !== undefined && currId === undefined) {
      // User just logged out
      if (sessionTokenRef.current) {
        endPlatformSession(sessionTokenRef.current);
        sessionTokenRef.current = null;
      }
    }

    prevUserIdRef.current = currId;
  }, [user?.id]);

  // ----- Public API -----
  const trackEvent = useCallback(
    (
      eventType: string,
      extras: Omit<PlatformEventPayload, 'session_token' | 'event_type'> = {}
    ) => {
      const token = sessionTokenRef.current;
      if (!token || !user) return;

      trackPlatformEvent({
        session_token: token,
        event_type: eventType,
        page: location.pathname,
        ...extras,
      });
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [user?.id, location.pathname]
  );

  return { trackEvent };
}
