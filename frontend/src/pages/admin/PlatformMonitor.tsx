/**
 * PlatformMonitor
 * Main entry point for the Platform Monitoring dashboard.
 * Displays real-time overview, online users, activity feed, and metric drill-downs.
 */
/**
 * PlatformMonitor Component (Modularized)
 * 
 * Provides a real-time dashboard of platform activity, including online users,
 * session metrics, and detailed activity logs.
 * 
 * @module PlatformMonitor
 */
import { usePlatformMonitor } from './platform-monitor/usePlatformMonitor';
import OverviewGrid from './platform-monitor/OverviewGrid';
import OnlineStatusPanel from './platform-monitor/OnlineStatusPanel';
import StatsPanels from './platform-monitor/StatsPanels';
import ActivityFeed from './platform-monitor/ActivityFeed';
import {
  OnlineModal,
  LoginsModal,
  QuestionsModal,
  SimulationsModal,
  EssaysModal,
  UserDetailModal
} from './platform-monitor/PlatformModals';

const PlatformMonitor = () => {
  const {
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
    openUser,
    closeModal,
    overview,
    ovLoading,
    loginData,
    qData,
    simData,
    essayData,
    activityData,
    actLoading
  } = usePlatformMonitor();

  return (
    <div className="py-6 space-y-8">
      {/* Page Title */}
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Monitoramento da Plataforma</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Rastreamento interno e nativo — sessões, eventos e métricas de uso em tempo real
        </p>
      </div>

      <OverviewGrid overview={overview} isLoading={ovLoading} />

      <OnlineStatusPanel 
        overview={overview} 
        onOpenOnline={() => setModal({ type: 'online' })} 
      />

      <StatsPanels 
        loginPeriod={loginPeriod}
        setLoginPeriod={setLoginPeriod}
        loginData={loginData}
        onOpenLogins={() => setModal({ type: 'logins', period: loginPeriod })}
        
        qPeriod={qPeriod}
        setQPeriod={setQPeriod}
        qData={qData}
        onOpenQuestions={() => setModal({ type: 'questions', period: qPeriod })}
        
        simPeriod={simPeriod}
        setSimPeriod={setSimPeriod}
        simData={simData}
        onOpenSimulations={() => setModal({ type: 'simulations', period: simPeriod })}
        
        essayPeriod={essayPeriod}
        setEssayPeriod={setEssayPeriod}
        essayData={essayData}
        onOpenEssays={() => setModal({ type: 'essays', period: essayPeriod })}
      />

      <ActivityFeed 
        activityData={activityData} 
        isLoading={actLoading} 
        onOpenUser={openUser} 
      />

      {/* --- Modals --- */}
      {modal?.type === 'online' && <OnlineModal onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'logins' && (
        <LoginsModal period={modal.period} onClose={closeModal} onUser={openUser} />
      )}
      {modal?.type === 'questions' && (
        <QuestionsModal period={modal.period} onClose={closeModal} onUser={openUser} />
      )}
      {modal?.type === 'simulations' && (
        <SimulationsModal period={modal.period} onClose={closeModal} onUser={openUser} />
      )}
      {modal?.type === 'essays' && (
        <EssaysModal period={modal.period} onClose={closeModal} onUser={openUser} />
      )}
      {modal?.type === 'user' && (
        <UserDetailModal userId={modal.id} onClose={closeModal} />
      )}
    </div>
  );
};

export default PlatformMonitor;
