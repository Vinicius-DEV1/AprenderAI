<?php

namespace App\Services;

use App\Models\Concurso;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConcursoSyncService
{
    /**
     * Sincroniza concursos de uma UF a partir da API externa.
     *
     * A API retorna um objeto JSON com as chaves:
     *   - concursos_abertos: array de {"Órgão": "...", "Vagas": "..."}
     *   - concursos_previstos: array de {"Órgão": "...", "Vagas": "..."}
     *
     * Em caso de erro HTTP ou exceção, loga e retorna sem apagar dados existentes.
     */
    public function syncByUf(string $uf): void
    {
        $base = rtrim(env('CONCURSOS_API_BASE', 'https://concursos-api.deno.dev'), '/');
        $url = $base . '/' . strtolower($uf);

        try {
            $response = Http::timeout(15)->get($url);

            if ($response->failed()) {
                Log::warning("ConcursoSyncService: erro HTTP {$response->status()} para UF {$uf}. URL: {$url}");
                return;
            }

            $payload = $response->json();

            if (!is_array($payload)) {
                Log::warning("ConcursoSyncService: resposta inesperada (não-array) para UF {$uf}.");
                return;
            }

            // Mapeia cada grupo para uma situação
            $grupos = [
                'concursos_abertos' => 'Inscrições Abertas',
                'concursos_previstos' => 'Previsto',
            ];

            foreach ($grupos as $chave => $situacao) {
                $items = $payload[$chave] ?? [];

                if (!is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    // Campo "Órgão" com acento — chave exata da API
                    $orgao = $item['Órgão'] ?? ($item['Orgao'] ?? ($item['orgao'] ?? ''));

                    if (empty($orgao)) {
                        continue;
                    }

                    // Vagas: pode vir como "Várias", "1.200" (string com ponto) ou número
                    $vagasRaw = $item['Vagas'] ?? ($item['vagas'] ?? null);
                    $vagas = null;
                    if ($vagasRaw !== null) {
                        $vagasClean = str_replace(['.', ' '], '', (string) $vagasRaw);
                        if (ctype_digit($vagasClean)) {
                            $vagas = (int) $vagasClean;
                        }
                        // Se for "Várias" ou texto não-numérico, mantém null
                    }

                    Concurso::updateOrCreate(
                        [
                            'uf' => strtoupper($uf),
                            'orgao' => $orgao,
                            'cargo' => null,
                        ],
                        [
                            'situacao' => $situacao,
                            'vagas' => $vagas,
                            'salario_maximo' => null,
                            'link_oficial' => $item['link_oficial'] ?? ($item['Link'] ?? null),
                            'fonte' => 'concursos-api.deno.dev',
                            'inscricoes_inicio' => null,
                            'inscricoes_fim' => null,
                            'ultimo_status_at' => now(),
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("ConcursoSyncService: exceção ao sincronizar UF {$uf}: " . $e->getMessage());
        }
    }
}
