{{--
|--------------------------------------------------------------------------
| Component: quota-limit-modal
|--------------------------------------------------------------------------
|
| A reusable modal displayed whenever a user reaches their monthly quota
| for a given resource (simulados, redações, etc.).
|
| It follows the same AlpineJS / x-modal.blade.php pattern already used
| in the application and is triggered via the browser event system:
|
|   window.dispatchEvent(new CustomEvent('open-modal', { detail: 'quota-limit-modal' }))
|
| PROPS:
|   $resource       — Display name of the blocked resource (e.g. "Simulados")
|   $used           — How many the user has consumed this cycle
|   $limit          — The effective limit for this cycle
|   $upgradeRoute   — Route string for the upgrade CTA button (default: plans.index)
|
--}}

@props([
    'resource'     => 'recurso',
    'used'         => 0,
    'limit'        => 0,
    'upgradeRoute' => 'plans.index',
])

{{-- Uses the existing <x-modal> component under the hood --}}
<x-modal name="quota-limit-modal" maxWidth="md">
    <div class="p-6">

        {{-- Icon --}}
        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-red-100 dark:bg-red-900/30 mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        {{-- Title --}}
        <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100 text-center mb-1">
            Limite de {{ $resource }} Atingido
        </h3>

        {{-- Usage indicator --}}
        <p class="text-sm text-gray-500 dark:text-slate-400 text-center mb-4">
            Você utilizou <strong class="text-gray-800 dark:text-slate-200">{{ $used }}</strong>
            de <strong class="text-gray-800 dark:text-slate-200">{{ $limit }}</strong>
            {{ Str::lower($resource) }} disponíveis neste ciclo mensal.
        </p>

        {{-- Progress bar (full = red) --}}
        <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2 mb-6">
            <div class="bg-red-500 h-2 rounded-full" style="width: 100%"></div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Primary: Upgrade CTA --}}
            <a href="{{ route($upgradeRoute) }}"
               class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700
                      text-white font-semibold rounded-lg transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                Fazer Upgrade de Plano
            </a>

            {{-- Secondary: Dismiss --}}
            <button type="button"
                    x-on:click="$dispatch('close-modal', 'quota-limit-modal')"
                    class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200
                           dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300
                           font-semibold rounded-lg transition-colors text-sm">
                Fechar
            </button>
        </div>
    </div>
</x-modal>
