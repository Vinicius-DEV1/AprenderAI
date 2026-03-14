-- ##########################################################################
-- SCRIPT DE LIMPEZA TOTAL: QUESTÕES DE CONCURSO PÚBLICO
-- ##########################################################################
-- Este script remove permanentemente todas as questões identificadas como 'concurso'
-- e garante que não restem registros órfãos em tabelas relacionadas.

START TRANSACTION;

-- --------------------------------------------------------------------------
-- 1. VERIFICAÇÃO INICIAL
-- --------------------------------------------------------------------------
SELECT COUNT(*) AS total_questoes_concurso_antes 
FROM questions 
WHERE type = 'concurso';

-- --------------------------------------------------------------------------
-- 2. LIMPEZA DE TABELAS PIVÔ (N:N)
-- --------------------------------------------------------------------------

-- Removendo associações com disciplinas
DELETE FROM question_subject 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Removendo associações com tópicos
DELETE FROM question_topic 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Removendo associações com conceitos semânticos (Xavier)
DELETE FROM question_concepts 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Removendo questões de cadernos/notebooks dos usuários
DELETE FROM notebook_questions 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');


-- --------------------------------------------------------------------------
-- 3. LIMPEZA DE ENTIDADES DEPENDENTES (1:N)
-- --------------------------------------------------------------------------

-- Alternativas da questão
DELETE FROM question_alternatives 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Imagens vinculadas
DELETE FROM question_images 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Logs de eventos de visualização/resposta
DELETE FROM question_event_logs 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Notas pessoais de usuários
DELETE FROM question_notes 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Denúncias/Reports de erros
DELETE FROM question_reports 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Respostas discursivas
DELETE FROM discursive_responses 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Histórico de respostas em simulados
DELETE FROM simulation_answers 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Histórico de respostas gerais
DELETE FROM user_question_answers 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Interações/Chats de IA por questão
DELETE FROM question_interactions 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Itens de fila de processamento de IA
DELETE FROM ai_batch_items 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Logs de requisições de IA
DELETE FROM ai_request_logs 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Logs de triagem/curadoria
DELETE FROM question_triage_logs 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Vetores e metadados de busca semântica (Qdrant Sync)
DELETE FROM question_vectors 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');

-- Logs de interação de busca
DELETE FROM search_interaction_logs 
WHERE question_id IN (SELECT id FROM questions WHERE type = 'concurso');


-- --------------------------------------------------------------------------
-- 4. EXCLUSÃO DA ENTIDADE PRINCIPAL
-- --------------------------------------------------------------------------

-- Removendo as questões (incluindo as marcadas com soft delete/deleted_at)
DELETE FROM questions 
WHERE type = 'concurso';


-- --------------------------------------------------------------------------
-- 5. VERIFICAÇÃO FINAL
-- --------------------------------------------------------------------------
SELECT COUNT(*) AS total_questoes_concurso_depois 
FROM questions 
WHERE type = 'concurso';

COMMIT;
