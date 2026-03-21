/**
 * usePlatformMonitor Hook
 * Encapsulates all queries and UI state for the Platform Monitoring dashboard.
 * Handles auto-refetches and period-based filtering.
 */
import { useState, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  getPlatformOverview,
  getPlatformLogins,
  getPlatformQuestions,
  getPlatformSimulations,
  getPlatformEssays,
  getPlatformActivity
} from '../../../api/platformMonitor';
import { Period, PlatformModalState } from './Types';

export function usePlatformMonitor() {
  // --- UI State ---
  const [loginPeriod, setLoginPeriod] = useState<Period>('today');
  const [qPeriod, setQPeriod] = useState<Period>('today');
  const [simPeriod, setSimPeriod] = useState<Period>('today');
  const [essayPeriod, setEssayPeriod] = useState<Period>('today');
  const [modal, setModal] = useState<PlatformModalState | null>(null);

  // --- Handlers ---
  const openUser = useCallback((id: number) => setModal({ type: 'user', id }), []);
  const closeModal = useCallback(() => setModal(null), []);

  // --- Queries ---

  // 1. Platform Overview (General Stats)
  const { data: overview, isLoading: ovLoading } = useQuery({
    queryKey: ['platform-overview'],
    queryFn: getPlatformOverview,
    refetchInterval: 30_000,
    staleTime: 20_000,
  });

  // 2. Login Metrics per Period
  const { data: loginData } = useQuery({
    queryKey: ['platform-logins', loginPeriod],
    queryFn: () => getPlatformLogins(loginPeriod),
    staleTime: 60_000,
  });

  // 3. Question Metrics per Period
  const { data: qData } = useQuery({
    queryKey: ['platform-questions', qPeriod],
    queryFn: () => getPlatformQuestions(qPeriod),
    staleTime: 120_000, // Questions are less volatile than logins
  });

  // 4. Simulation Metrics per Period
  const { data: simData } = useQuery({
    queryKey: ['platform-simulations', simPeriod],
    queryFn: () => getPlatformSimulations(simPeriod),
    staleTime: 120_000,
  });

  // 5. Essay Metrics per Period
  const { data: essayData } = useQuery({
    queryKey: ['platform-essays', essayPeriod],
    queryFn: () => getPlatformEssays(essayPeriod),
    staleTime: 120_000,
  });

  // 6. Real-time Activity Feed
  const { data: activityData, isLoading: actLoading } = useQuery({
    queryKey: ['platform-activity'],
    queryFn: getPlatformActivity,
    refetchInterval: 30_000,
    staleTime: 20_000,
  });

  return {
    // State
    loginPeriod, 
    setLoginPeriod,
    qPeriod, 
    setQPeriod,
    simPeriod, 
    setSimPeriod,
    essayPeriod, 
    setEssayPeriod,
    modal,
    setModal,
    
    // Handlers
    openUser,
    closeModal,
    
    // Data
    overview,
    ovLoading,
    loginData,
    qData,
    simData,
    essayData,
    activityData,
    actLoading
  };
}
