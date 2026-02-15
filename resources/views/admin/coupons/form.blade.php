<x-layouts.admin>
    <div class="mb-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.coupons.index') }}"
                class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                    </path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">
                {{ isset($coupon) ? 'Editar Cupom' : 'Novo Cupom' }}
            </h1>
        </div>
        <p class="text-gray-600 mt-2 ml-9">
            {{ isset($coupon) ? 'Atualize as informações do cupom.' : 'Preencha os dados para criar um novo cupom.' }}
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl">
        <form
            action="{{ isset($coupon) ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
            method="POST" class="space-y-6">
            @csrf
            @if (isset($coupon))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Código do Cupom</label>
                    <input type="text" name="code" id="code"
                        value="{{ old('code', $coupon->code ?? '') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase"
                        placeholder="EX: PROMO2024" required>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="flex items-center h-full pt-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active', $coupon->is_active ?? true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-gray-700 font-medium">Cupom Ativo</span>
                    </label>
                </div>

                <!-- Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Desconto</label>
                    <select name="type" id="type"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required>
                        <option value="percent"
                            {{ old('type', $coupon->type ?? '') == 'percent' ? 'selected' : '' }}>Porcentagem (%)
                        </option>
                        <option value="fixed" {{ old('type', $coupon->type ?? '') == 'fixed' ? 'selected' : '' }}>
                            Valor Fixo (R$)</option>
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Value -->
                <div>
                    <label for="value" class="block text-sm font-medium text-gray-700 mb-1">Valor do
                        Desconto</label>
                    <input type="number" name="value" id="value" step="0.01" min="0"
                        value="{{ old('value', $coupon->value ?? '') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="0.00" required>
                    @error('value')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Max Uses -->
                <div>
                    <label for="max_uses" class="block text-sm font-medium text-gray-700 mb-1">Limite de Usos
                        (Global)</label>
                    <input type="number" name="max_uses" id="max_uses" min="1"
                        value="{{ old('max_uses', $coupon->max_uses ?? '') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Ilimitado se vazio">
                    <p class="mt-1 text-xs text-gray-500">Deixe em branco para usos ilimitados.</p>
                    @error('max_uses')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Used Count (Read Only) -->
                @if (isset($coupon))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade Já Utilizada</label>
                        <input type="text" value="{{ $coupon->used_count }}" disabled
                            class="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm">
                    </div>
                @endif

                <!-- Start Date -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Válido a partir
                        de</label>
                    <input type="datetime-local" name="start_date" id="start_date"
                        value="{{ old('start_date', isset($coupon->start_date) ? $coupon->start_date->format('Y-m-d\TH:i') : '') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('start_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expires At -->
                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">Válido até</label>
                    <input type="datetime-local" name="expires_at" id="expires_at"
                        value="{{ old('expires_at', isset($coupon->expires_at) ? $coupon->expires_at->format('Y-m-d\TH:i') : '') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('expires_at')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end pt-6 border-t border-gray-100">
                <a href="{{ route('admin.coupons.index') }}"
                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                    Cancelar
                </a>
                <button type="submit"
                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    {{ isset($coupon) ? 'Atualizar Cupom' : 'Criar Cupom' }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
