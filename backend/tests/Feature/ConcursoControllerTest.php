<?php

namespace Tests\Feature;

use App\Models\Concurso;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcursoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/concursos');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_can_access_concursos(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free']);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($user)->get(route('concursos.index'));

        $response->assertStatus(200);
        $response->assertSee('Radar de Concursos');
    }

    public function test_filter_by_uf(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free']);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        Concurso::create([
            'uf' => 'SP',
            'orgao' => 'Órgão São Paulo',
            'situacao' => 'Inscrições Abertas',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        Concurso::create([
            'uf' => 'RJ',
            'orgao' => 'Órgão Rio de Janeiro',
            'situacao' => 'Inscrições Abertas',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('concursos.index', ['uf' => 'SP']));

        $response->assertStatus(200);
        $response->assertSee('Órgão São Paulo');
        $response->assertDontSee('Órgão Rio de Janeiro');
    }

    public function test_filter_by_busca(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free']);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        Concurso::create([
            'uf' => 'MG',
            'orgao' => 'Polícia Federal',
            'situacao' => 'Inscrições Abertas',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        Concurso::create([
            'uf' => 'MG',
            'orgao' => 'IBGE',
            'situacao' => 'Inscrições Abertas',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('concursos.index', ['busca' => 'Polícia']));

        $response->assertStatus(200);
        $response->assertSee('Polícia Federal');
        $response->assertDontSee('IBGE');
    }

    public function test_situacao_todos_shows_all(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free']);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        Concurso::create([
            'uf' => 'BA',
            'orgao' => 'Órgão Encerrado',
            'situacao' => 'Encerrado',
            'fonte' => 'concursos-api.deno.dev',
            'ultimo_status_at' => now(),
        ]);

        // Sem filtro situacao=todos, encerrado não aparece
        $response = $this->actingAs($user)->get(route('concursos.index'));
        $response->assertDontSee('Órgão Encerrado');

        // Com situacao=todos, encerrado aparece
        $response = $this->actingAs($user)->get(route('concursos.index', ['situacao' => 'todos']));
        $response->assertSee('Órgão Encerrado');
    }
}
