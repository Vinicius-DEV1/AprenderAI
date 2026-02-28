# Troubleshooting: Motor de Presets - Erro 500 / 405 Method Not Allowed

## O Problema
Ao tentar criar um novo Preset através do painel Administrativo (`/admin/simulations/builder`), a interface retornava um alerta genérico de erro. Investigando a aba Network ou os logs do Laravel, o request emitia o HTTP Status code `405 Method Not Allowed` informando The POST method is not supported for route api/v1/admin/simulations/presets. Supported methods: GET, HEAD.

Ocasionalmente, isso era apresentado como um erro na camada do Frontend por ser capturado pelo interceptor do Axios.

## Causa Raiz
1. **Cache de Código e OPcache Obsoleto:** O container responsável pelo processamento do PHP (PHP-FPM, no caso o backend da aplicação) estava segurando uma versão compilada antiga dos arquivos de rotas (`routes/api.php`). Mesmo quando `Route::post` estava explicitamente escrito e listado via `php artisan route:list`, a requisição processada sofria match num fallback incorreto por causa do OPcache desatualizado.
2. **Exceção Escondida do Banco de Dados:** Originalmente o bloco dentro do controlador em `AdminSimulationController@store` não possuía um bloco `try-catch` capturando corretamente validações de banco e gerando retorno em formato JSON amigável na response, causando HTML injetado no Axios que mascarava o erro original.

## Solução Implementada
1. **Clear OPcache Container:** Nós rodamos limpeza do cache (`php artisan optimize:clear`) do Laravel, porém mais importante foi **reiniciar diretamente o container PHP FPM (`aprender-ai-app`)** - `docker restart aprender-ai-app`. Isso forçou o OPcache a ler a sintaxe moderna do arquivo `api.php`.
2. **Defesa no Controller:** No método `store` de `AdminSimulationController.php` a transaction do model foi encapsulada em um bloqueio `try-catch` retornando formalmente código 500 e registrando o Log.

```php
// app/Http/Controllers/Api/Admin/AdminSimulationController.php

try {
    return DB::transaction(function () use ($validated) {
        $preset = SimulationPreset::create([...]);
        // ... rules relations
        return response()->json($preset->load('rules'), 201);
    });
} catch (\Exception $e) {
    \Illuminate\Support\Facades\Log::error('Erro ao salvar Preset: ' . $e->getMessage());
    return response()->json(['message' => 'Erro interno ao salvar'], 500);
}
```

## Como Evitar Erros Futuros
- Sempre garanta que `php artisan optimize:clear` ou um reload no FPM/Docker container ocorram ao declarar novas rotas na API ou caso haja discrepância com o output de request na aba Network vs `php artisan route:list`.
- Todas as APIs de front modernizadas (React SPA) cujo Controller não utilize recursos Form Requests devem, obrigatoriamente, envolver lógica CRUD num Try-Catch para que o Frontend possa exibir um erro via Notificação elegante (Sonner) invés de falhar o parse de URL/JSON.
