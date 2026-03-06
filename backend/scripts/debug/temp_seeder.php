<?php
$user = App\Models\User::firstOrCreate(
    ['email' => 'admin@aprenderai.com'],
    [
        'name' => 'Admin Test',
        'password' => bcrypt('Aprova@123'),
        'email_verified_at' => now(),
    ]
);

App\Models\Essay::create([
    'user_id' => $user->id,
    'title' => 'Receita de bolo',
    'content' => 'Receita com muita cenoura...',
    'type' => 'enem',
    'status' => 'completed',
    'score' => 0,
    'off_topic' => true,
    'off_topic_reason' => 'Você fugiu do tema.',
    'final_score_locked' => true,
    'evaluated_at' => now(),
    'feedback_json' => [
        'score' => 0,
        'summary' => 'Fuga do tema: nota 0.',
        'strengths' => [],
        'weaknesses' => ['Fuga do tema propoto.'],
        'corrections' => [],
        'improved_version' => '',
        'competencies' => []
    ]
]);

App\Models\Essay::create([
    'user_id' => $user->id,
    'title' => 'Os desafios da saúde',
    'content' => 'A saúde pública no Brasil enfrenta...',
    'type' => 'enem',
    'status' => 'completed',
    'score' => 840,
    'evaluated_at' => now(),
    'feedback_json' => [
        'score' => 840,
        'summary' => 'Boa redação',
        'strengths' => ['Bons argumentos'],
        'weaknesses' => ['Amplo'],
        'corrections' => [['original' => 'enfrenta', 'corrected' => 'tem enfrentado', 'explanation' => 'melhor']],
        'improved_version' => 'A saúde...',
        'competencies' => [
            ['name' => 'C1', 'score' => 160, 'justification' => 'Bom domínio', 'what_was_done' => 'O que foi feito 1', 'how_to_improve' => 'Como melhorar 1'],
            ['name' => 'C2', 'score' => 200, 'justification' => 'Excelente', 'what_was_done' => '...', 'how_to_improve' => '...'],
            ['name' => 'C3', 'score' => 160, 'justification' => 'Válidos', 'what_was_done' => '...', 'how_to_improve' => '...'],
            ['name' => 'C4', 'score' => 160, 'justification' => 'Uso de conectivos', 'what_was_done' => '...', 'how_to_improve' => '...'],
            ['name' => 'C5', 'score' => 160, 'justification' => 'Proposta', 'what_was_done' => '...', 'how_to_improve' => '...']
        ]
    ]
]);
echo "Essays created successfully!\n";
