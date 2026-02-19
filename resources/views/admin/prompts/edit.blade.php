@extends('layouts.admin')

@section('title', 'Editar Prompt - Admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.prompts.index') }}" class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Editar Prompt</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Slug: <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ $systemPrompt->slug }}</span></p>
        </div>
    </div>

    @if($systemPrompt->variables)
    <div class="bg-blue-50 dark:bg-blue-950/30 border-l-4 border-blue-500 p-4 rounded-r">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    <strong>Variáveis disponíveis:</strong>
                    {{ implode(', ', array_map(fn($v) => '{' . $v . '}', $systemPrompt->variables)) }}
                </p>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white dark:bg-slate-900 shadow-sm rounded-xl border border-slate-200 dark:border-slate-700">
        <form action="{{ route('admin.prompts.update', $systemPrompt) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label for="title" class="text-sm font-medium text-slate-700 dark:text-slate-300">Título</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $systemPrompt->title) }}" 
                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="description" class="text-sm font-medium text-slate-700 dark:text-slate-300">Descrição</label>
                    <input type="text" name="description" id="description" value="{{ old('description', $systemPrompt->description) }}" 
                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-2">
                <label for="content" class="text-sm font-medium text-slate-700 dark:text-slate-300">Conteúdo do Prompt</label>
                <textarea name="content" id="content" rows="15" 
                    class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white font-mono text-xs focus:ring-indigo-500 focus:border-indigo-500">{{ old('content', $systemPrompt->content) }}</textarea>
                @error('content') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-700">
                <a href="{{ route('admin.prompts.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
@endsection
