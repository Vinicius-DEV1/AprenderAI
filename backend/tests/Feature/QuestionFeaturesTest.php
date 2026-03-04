<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_toggle_favorite_question()
    {
        $user = User::factory()->create();
        $question = \App\Models\Question::factory()->create();

        $this->actingAs($user);

        // Adiciona aos favoritos
        $response1 = $this->postJson("/api/v1/questions/{$question->id}/favorite");
        $response1->assertStatus(200)
            ->assertJsonPath('is_favorite', true);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'question_id' => $question->id,
        ]);

        // Remove dos favoritos (toggle)
        $response2 = $this->postJson("/api/v1/questions/{$question->id}/favorite");
        $response2->assertStatus(200)
            ->assertJsonPath('is_favorite', false);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'question_id' => $question->id,
        ]);
    }

    /** @test */
    public function user_can_report_a_problem_and_admin_can_deactivate()
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $question = \App\Models\Question::factory()->create(['is_active' => true]);

        // 1. Aluno reporta
        $this->actingAs($student);
        $res = $this->postJson("/api/v1/questions/{$question->id}/report", [
            'reason' => 'A alternativa C está incorreta.',
        ]);
        $res->assertStatus(201);

        $this->assertDatabaseHas('question_reports', [
            'question_id' => $question->id,
            'user_id' => $student->id,
            'status' => 'pending'
        ]);

        // 2. Admin visualiza e desativa a questão
        $this->actingAs($admin);

        // Verifica se a questão aparece agrupada na listagem de reports
        $listRes = $this->getJson('/api/v1/admin/question-reports?status=pending');
        $listRes->assertStatus(200);
        $this->assertEquals($question->id, $listRes->json('data.0.id'));

        // Desativa a questão
        $deactivateRes = $this->postJson("/api/v1/admin/questions/{$question->id}/deactivate");
        $deactivateRes->assertStatus(200);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'is_active' => false
        ]);

        $this->assertDatabaseHas('question_reports', [
            'question_id' => $question->id,
            'status' => 'resolved'
        ]);
    }

    /** @test */
    public function user_can_manage_notes()
    {
        $user = User::factory()->create();
        $question = \App\Models\Question::factory()->create();

        $this->actingAs($user);

        // Criar nota
        $res1 = $this->postJson("/api/v1/questions/{$question->id}/notes", [
            'content' => 'Lembrar da fórmula de Bhaskara'
        ]);
        $res1->assertStatus(201);
        $noteId = $res1->json('note.id');

        // Atualizar nota
        $res2 = $this->putJson("/api/v1/notes/{$noteId}", [
            'content' => 'Lembrar da fórmula de Bhaskara e Delta'
        ]);
        $res2->assertStatus(200);

        // Excluir nota
        $res3 = $this->deleteJson("/api/v1/notes/{$noteId}");
        $res3->assertStatus(200);

        $this->assertDatabaseMissing('question_notes', ['id' => $noteId]);
    }
}
