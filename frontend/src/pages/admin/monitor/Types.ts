/**
 * TopProcess
 * Represents a system process on the host machine.
 */
export interface TopProcess {
    pid: string;
    name: string;
    cpu: number;
    mem_bytes: number;
}

/**
 * ServiceHealth
 * Status of core system dependencies.
 */
export interface ServiceHealth {
    database: boolean;
    redis: boolean;
    app: boolean;
    webserver: boolean;
}

/**
 * QueueStats
 * High-level counts for background job processing.
 */
export interface QueueStats {
    pending: number;
    failed: number;
    workers: string;
}

/**
 * PerformanceMetric
 * Throughput and latency metrics for queue workers.
 */
export interface PerformanceMetric {
    jobs_per_minute: number;
    avg_duration_seconds: number;
}

/**
 * RealtimeData
 * Snapshot of current system resource usage and service health.
 */
export interface RealtimeData {
    cpu_usage: number;
    ram_usage: number;
    ram_used_gb: number;
    ram_total_gb: number;
    disk_usage: number;
    disk_used_gb: number;
    disk_total_gb: number;
    net_rx_speed: number;
    net_tx_speed: number;
    uptime?: string;
    services?: ServiceHealth;
    queues?: QueueStats;
    queue_performance?: Record<string, PerformanceMetric>;
    top_processes?: TopProcess[];
}

/**
 * QueueJob
 * Basic information for a pending or processing job in a Laravel queue.
 */
export interface QueueJob {
    id: number;
    queue: string;
    name: string;
    attempts: number;
    is_processing: boolean;
    created_at: string;
}

/**
 * FailedJob
 * Details for a job that has moved to the failed_jobs table.
 */
export interface FailedJob {
    id: number;
    queue: string;
    name: string;
    exception: string;
    failed_at: string;
}

/**
 * CompletedBatch
 * Statistics for a Laravel job batch that has finished execution.
 */
export interface CompletedBatch {
    id: string;
    name: string;
    total_jobs: number;
    failed_jobs: number;
    finished_at: string;
}

/**
 * QueuesData
 * Collection of all queue-related data for the Monitor UI.
 */
export interface QueuesData {
    jobs: QueueJob[];
    failed: FailedJob[];
    completed: CompletedBatch[];
}
