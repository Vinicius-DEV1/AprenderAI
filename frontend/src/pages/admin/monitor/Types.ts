export interface TopProcess {
    pid: string;
    name: string;
    cpu: number;
    mem_bytes: number;
}

export interface ServiceHealth {
    database: boolean;
    redis: boolean;
    app: boolean;
    webserver: boolean;
}

export interface QueueStats {
    pending: number;
    failed: number;
    workers: string;
}

export interface PerformanceMetric {
    jobs_per_minute: number;
    avg_duration_seconds: number;
}

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

export interface QueueJob {
    id: number;
    queue: string;
    name: string;
    attempts: number;
    is_processing: boolean;
    created_at: string;
}

export interface FailedJob {
    id: number;
    queue: string;
    name: string;
    exception: string;
    failed_at: string;
}

export interface CompletedBatch {
    id: string;
    name: string;
    total_jobs: number;
    failed_jobs: number;
    finished_at: string;
}

export interface QueuesData {
    jobs: QueueJob[];
    failed: FailedJob[];
    completed: CompletedBatch[];
}
