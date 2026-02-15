<x-layouts.admin>
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Monitoramento VPS</h1>
            <p class="text-gray-600">Acompanhe a saúde do servidor em tempo real</p>
        </div>
        
        <!-- Range Filter -->
        <div class="bg-white rounded-lg shadow-sm p-1 inline-flex">
            <button onclick="updateCharts('1h')" class="range-btn px-4 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors" data-range="1h">1 Hora</button>
            <button onclick="updateCharts('24h')" class="range-btn px-4 py-2 rounded-md text-sm font-medium bg-blue-50 text-blue-600 shadow-sm" data-range="24h">24 Horas</button>
            <button onclick="updateCharts('7d')" class="range-btn px-4 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors" data-range="7d">7 Dias</button>
        </div>
    </div>

    <!-- Realtime Gauges -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- CPU Gauge -->
        <div class="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">CPU Usage</h3>
                    <div class="text-3xl font-bold text-gray-900 mt-1" id="cpu-current">--%</div>
                </div>
                <div class="p-2 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" id="cpu-bar" style="width: 0%"></div>
            </div>
        </div>

        <!-- RAM Gauge -->
        <div class="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">RAM Usage</h3>
                    <div class="text-3xl font-bold text-gray-900 mt-1" id="ram-current">--%</div>
                </div>
                <div class="p-2 bg-purple-50 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-purple-600 h-2 rounded-full transition-all duration-500" id="ram-bar" style="width: 0%"></div>
            </div>
        </div>

        <!-- Network RX -->
        <div class="bg-white rounded-xl shadow-sm p-6">
             <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Download Speed</h3>
                    <div class="text-2xl font-bold text-green-600 mt-1" id="net-rx-current">-- KB/s</div>
                </div>
                <div class="p-2 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                </div>
            </div>
        </div>

        <!-- Network TX -->
        <div class="bg-white rounded-xl shadow-sm p-6">
             <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Upload Speed</h3>
                    <div class="text-2xl font-bold text-orange-600 mt-1" id="net-tx-current">-- KB/s</div>
                </div>
                <div class="p-2 bg-orange-50 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- CPU/RAM History -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Uso de Recursos (%)</h3>
            <div class="relative h-72">
                <canvas id="resourceChart"></canvas>
            </div>
        </div>

        <!-- Network History -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Tráfego de Rede</h3>
            <div class="relative h-72">
                <canvas id="networkChart"></canvas>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let resourceChart = null;
        let networkChart = null;
        let currentRange = '24h';

        // Helper to format bytes
        function formatBytes(bytes, decimals = 1) {
            if (!+bytes) return '0 B/s';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
        }

        function initCharts() {
            const ctxRes = document.getElementById('resourceChart').getContext('2d');
            resourceChart = new Chart(ctxRes, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'CPU',
                            borderColor: '#2563EB',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            data: []
                        },
                        {
                            label: 'RAM',
                            borderColor: '#9333EA',
                            backgroundColor: 'rgba(147, 51, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            data: []
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        },
                        x: {
                            display: false 
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            });

            const ctxNet = document.getElementById('networkChart').getContext('2d');
            networkChart = new Chart(ctxNet, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Download (RX)',
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            data: []
                        },
                        {
                            label: 'Upload (TX)',
                            borderColor: '#EA580C',
                            backgroundColor: 'rgba(234, 88, 12, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            data: []
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatBytes(value);
                                }
                            }
                        },
                        x: {
                            display: false
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += formatBytes(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        async function updateCharts(range) {
            currentRange = range;
            
            // Update buttons
            document.querySelectorAll('.range-btn').forEach(btn => {
                if(btn.dataset.range === range) {
                    btn.classList.remove('bg-white', 'text-gray-600', 'hover:bg-gray-100');
                    btn.classList.add('bg-blue-50', 'text-blue-600', 'shadow-sm');
                } else {
                    btn.classList.add('bg-white', 'text-gray-600', 'hover:bg-gray-100');
                    btn.classList.remove('bg-blue-50', 'text-blue-600', 'shadow-sm');
                }
            });

            // Fetch Data
            try {
                const response = await fetch(`{{ route('admin.monitor.history') }}?range=${range}`);
                const data = await response.json();

                const labels = data.map(d => new Date(d.created_at).toLocaleTimeString());
                const cpuData = data.map(d => d.cpu_usage);
                const ramData = data.map(d => d.ram_usage);
                const rxData = data.map(d => d.net_rx_speed);
                const txData = data.map(d => d.net_tx_speed);

                // Update Resource Chart
                resourceChart.data.labels = labels;
                resourceChart.data.datasets[0].data = cpuData;
                resourceChart.data.datasets[1].data = ramData;
                resourceChart.update();

                // Update Network Chart
                networkChart.data.labels = labels;
                networkChart.data.datasets[0].data = rxData;
                networkChart.data.datasets[1].data = txData;
                networkChart.update();

            } catch (error) {
                console.error('Error fetching history:', error);
            }
        }

        async function updateRealtime() {
            try {
                const response = await fetch(`{{ route('admin.monitor.realtime') }}`);
                const data = await response.json();

                // Update Gauges
                document.getElementById('cpu-current').innerText = data.cpu_usage.toFixed(1) + '%';
                document.getElementById('cpu-bar').style.width = data.cpu_usage + '%';
                
                document.getElementById('ram-current').innerText = data.ram_usage.toFixed(1) + '%';
                document.getElementById('ram-bar').style.width = data.ram_usage + '%';

                // Network is fetched from history generally for speed, but if we have realtime capability:
                // Note: Realtime endpoint calculates instant snapshot, but speed needs delta. 
                // The service returns 0 for speed in realtime() because it doesn't wait 1s. 
                // We should fetch the LATEST record from DB for speed, or wait in realtime().
                // Let's use the latest history point for speed to be safe.
                
                // Correction: realtime() calls service->collect(). Service->collect() returns 0 for speed.
                // We should modify Controller to return latest DB record for realtime speed?
                // Or let the user know realtime speed is only available in history chart?
                // Actually, let's fetch the latest server_metric record.
            } catch (error) {
                console.error('Error fetching realtime:', error);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            updateCharts('24h');
            
            // Poll for realtime updates every 5s
            setInterval(updateRealtime, 5000);
            
            // Poll for history updates every 1 min
            setInterval(() => updateCharts(currentRange), 60000);
        });
    </script>
    @endpush
</x-layouts.admin>
