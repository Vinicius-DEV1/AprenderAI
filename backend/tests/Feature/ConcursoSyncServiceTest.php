<?php

namespace Tests\Feature;

use App\Models\Concurso;
use App\Services\ConcursoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConcursoSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Retorna um payload fake compatível com a estrutura real da API:
     * { "concursos_abertos": [{"Órgão": "...", "Vagas": "..."}],
     *   "concursos_previstos": [...] }
     */
    private function fakePayload(array $abertos = [], array $previstos = []): array
    {
        return [
            'desenvolvido_por' => 'Test',
            'estado' => 'São Paulo',
            'uf' => 'sp',
            'concursos_abertos' => $abertos,
            'concursos_previstos' => $previstos,
        ];
    }

    public function test_sync_creates_concursos_from_api(): void
    {
        Http::fake([
            'concursos-api.deno.dev/sp' => Http::response(
                $this->fakePayload(
                    abertos: [
                        ['Órgão' => 'Prefeitura de São Paulo', 'Vagas' => '500'],
                    ],
                    previstos: [
                        ['Órgão' => 'IBGE previsto', 'Vagas' => '1000'],
                    ]
                ),
                200
            ),
        ]);

        $service = app(ConcursoSyncService::class);
        $service->syncByUf('SP');

        $this->assertDatabaseHas('concursos', [
            'uf' => 'SP',
            'orgao' => 'Prefeitura de São Paulo',
            'situacao' => 'Inscrições Abertas',
            'vagas' => 500,
        ]);

        $this->assertDatabaseHas('concursos', [
            'uf' => 'SP',
            'orgao' => 'IBGE previsto',
            'situacao' => 'Previsto',
        ]);

        $this->assertDatabaseCount('concursos', 2);
    }

    public function test_sync_updates_existing_concurso(): void
    {
        Concurso::create([
            'uf' => 'SP',
            'orgao' => 'Tribunal de Justiça',
            'cargo' => null,
            'situacao' => 'Previsto',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now()->subDay(),
        ]);

        Http::fake([
            'concursos-api.deno.dev/sp' => Http::response(
                $this->fakePayload(
                    abertos: [
                        ['Órgão' => 'Tribunal de Justiça', 'Vagas' => '30'],
                    ]
                ),
                200
            ),
        ]);

        $service = app(ConcursoSyncService::class);
        $service->syncByUf('SP');

        // Deve continuar sendo apenas 1 registro (updateOrCreate)
        $this->assertDatabaseCount('concursos', 1);
        $this->assertDatabaseHas('concursos', [
            'orgao' => 'Tribunal de Justiça',
            'situacao' => 'Inscrições Abertas',
            'vagas' => 30,
        ]);
    }

    public function test_sync_on_http_error_does_not_delete_existing_data(): void
    {
        Concurso::create([
            'uf' => 'RJ',
            'orgao' => 'Polícia Civil RJ',
            'cargo' => null,
            'situacao' => 'Previsto',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        Http::fake([
            'concursos-api.deno.dev/rj' => Http::response([], 500),
        ]);

        $service = app(ConcursoSyncService::class);
        $service->syncByUf('RJ');

        // Registro existente deve permanecer intacto
        $this->assertDatabaseHas('concursos', [
            'uf' => 'RJ',
            'orgao' => 'Polícia Civil RJ',
        ]);
        $this->assertDatabaseCount('concursos', 1);
    }

    public function test_sync_on_exception_does_not_crash(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Timeout');
        });

        $service = app(ConcursoSyncService::class);

        // Não deve lançar exceção
        $service->syncByUf('MG');

        $this->assertDatabaseCount('concursos', 0);
    }

    public function test_sync_handles_vagas_string_gracefully(): void
    {
        Http::fake([
            'concursos-api.deno.dev/ba' => Http::response(
                $this->fakePayload(
                    abertos: [
                        ['Órgão' => 'SEDUC BA', 'Vagas' => 'Várias'],
                        ['Órgão' => 'PM BA', 'Vagas' => '1.500'],
                    ]
                ),
                200
            ),
        ]);

        $service = app(ConcursoSyncService::class);
        $service->syncByUf('BA');

        // "Várias" → vagas deve ser null
        $this->assertDatabaseHas('concursos', ['orgao' => 'SEDUC BA', 'vagas' => null]);
        // "1.500" → vagas deve ser 1500
        $this->assertDatabaseHas('concursos', ['orgao' => 'PM BA', 'vagas' => 1500]);
    }

    public function test_sync_populates_link_oficial_with_search_url_when_api_has_no_link(): void
    {
        Http::fake([
            'concursos-api.deno.dev/sp' => Http::response(
                $this->fakePayload(
                    abertos: [
                        ['Órgão' => 'Prefeitura de São Paulo', 'Vagas' => '200'],
                    ]
                ),
                200
            ),
        ]);

        $service = app(ConcursoSyncService::class);
        $service->syncByUf('SP');

        $concurso = Concurso::where('orgao', 'Prefeitura de São Paulo')->first();

        $this->assertNotNull($concurso->link_oficial, 'link_oficial deve ser preenchido mesmo sem link na API');
        $this->assertStringContainsString('google.com/search', $concurso->link_oficial);
        $this->assertStringContainsString('concurso', $concurso->link_oficial);
        $this->assertStringContainsString('S%C3%A3o%20Paulo', $concurso->link_oficial);
    }
}
