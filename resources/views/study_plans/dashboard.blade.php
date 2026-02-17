<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Seu Plano de Estudos') }}
            </h2>
            <form action="{{ route('study-plan.update') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 font-medium disabled:opacity-50"
                    @if($plan->next_update_at && $plan->next_update_at->isFuture()) disabled
                    title="Disponível em {{ $plan->next_update_at->format('d/m/Y') }}" @endif>
                    Atualizar Plano
                    @if($plan->next_update_at && $plan->next_update_at->isFuture())
                        (em {{ $plan->next_update_at->diffForHumans() }})
                    @endif
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <!-- Overview -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-slate-900 mb-2">Visão Geral</h3>
                <p class="text-slate-700 italic">
                    "{{ $plan->plan_json['overview'] ?? 'N/A' }}"
                </p>
            </div>

            <!-- Focus + Analysis -->
            <div class="grid md:grid-cols-3 gap-6">

                <!-- Focus Points -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="font-bold text-slate-900 mb-4">
                        Pontos de Atenção
                    </h4>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-600">
                        @foreach($plan->plan_json['focus_points'] ?? [] as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>

                <!-- Sua Análise -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 md:col-span-2">
                    <h4 class="font-bold text-slate-900 mb-6">Sua Análise</h4>

                    @php
                        $userId = auth()->id();

                        $dbWeak = \App\Models\UserTopicStat::where('user_id', $userId)
                            ->where('attempts', '>=', 2)
                            ->where('topic', '!=', 'Geral')
                            ->where('accuracy', '<=', 55)
                            ->orderBy('accuracy')
                            ->limit(5)
                            ->get();

                        $dbStrong = \App\Models\UserTopicStat::where('user_id', $userId)
                            ->where('attempts', '>=', 2)
                            ->where('topic', '!=', 'Geral')
                            ->where('accuracy', '>=', 60)
                            ->orderByDesc('accuracy')
                            ->limit(5)
                            ->get();
                    @endphp

                    <div class="grid md:grid-cols-2 gap-8">

                        <div>
                            <h5 class="text-sm font-semibold text-red-600 mb-3 uppercase">
                                Pontos Fracos
                            </h5>

                            @forelse($dbWeak as $item)
                                <div class="mb-3 text-sm">
                                    <div class="font-medium text-slate-800">
                                        {{ $item->topic }} — {{ $item->subject }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ number_format($item->accuracy, 0) }}% • {{ $item->attempts }} tentativas
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500 italic">
                                    Complete mais simulados para identificar padrões reais.
                                </p>
                            @endforelse
                        </div>

                        <div>
                            <h5 class="text-sm font-semibold text-green-600 mb-3 uppercase">
                                Pontos Fortes
                            </h5>

                            @forelse($dbStrong as $item)
                                <div class="mb-3 text-sm">
                                    <div class="font-medium text-slate-800">
                                        {{ $item->topic }} — {{ $item->subject }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ number_format($item->accuracy, 0) }}% • {{ $item->attempts }} tentativas
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500 italic">
                                    Continue resolvendo questões para consolidar seus pontos fortes.
                                </p>
                            @endforelse
                        </div>

                    </div>
                </div>
            </div>

            <!-- Cronograma -->
            <div class="bg-white shadow-sm sm:rounded-lg p-8">
                <h3 class="text-2xl font-bold text-slate-900 mb-8">
                    Cronograma Semanal
                </h3>

                @php
                    $schedule = $plan->plan_json['weekly_schedule'] ?? [];

                    $weekOrder = [
                        'segunda' => 1,
                        'terca' => 2,
                        'terça' => 2,
                        'quarta' => 3,
                        'quinta' => 4,
                        'sexta' => 5,
                        'sabado' => 6,
                        'sábado' => 6,
                        'domingo' => 7,
                    ];

                    uksort($schedule, function ($a, $b) use ($weekOrder) {
                        $aSlug = \Illuminate\Support\Str::slug($a);
                        $bSlug = \Illuminate\Support\Str::slug($b);

                        $aWeight = 999;
                        $bWeight = 999;

                        foreach ($weekOrder as $day => $weight) {
                            if (str_contains($aSlug, $day)) {
                                $aWeight = $weight;
                                break;
                            }
                        }

                        foreach ($weekOrder as $day => $weight) {
                            if (str_contains($bSlug, $day)) {
                                $bWeight = $weight;
                                break;
                            }
                        }

                        return $aWeight <=> $bWeight;
                    });
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                    @foreach($schedule as $day => $tasks)
                        <div class="bg-slate-50 border rounded-2xl p-6 shadow-sm">

                            <h4 class="text-lg font-bold text-blue-700 mb-5 capitalize">
                                {{ $day }}
                            </h4>

                            <ul class="space-y-3 text-sm">
                                @foreach((array) $tasks as $task)
                                    <li class="flex gap-3 items-start text-slate-600">
                                        <span class="mt-1 h-3 w-3 rounded-full bg-blue-500"></span>
                                        <span>{{ $task }}</span>
                                    </li>
                                @endforeach
                            </ul>

                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Metodologia -->
            <div class="bg-blue-50 border border-blue-200 sm:rounded-lg p-8">
                <h4 class="text-lg font-bold text-blue-900 mb-4">
                    Metodologia Sugerida
                </h4>

                <p class="text-blue-800 leading-relaxed text-base">
                    {{ $plan->plan_json['methodology'] ?? 'N/A' }}
                </p>
            </div>

        </div>
    </div>
</x-app-layout>