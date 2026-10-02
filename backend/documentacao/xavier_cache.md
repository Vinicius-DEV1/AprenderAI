# Cache Semântico de Busca Inteligente (Xavier) 🚀

Este documento descreve a arquitetura técnica da feature de Search Cache que reduz a latência média do agente de busca de `~4.5s` para `~200ms` usando modelos de *Embedding*.

## Arquitetura de Dois Níveis (L1 / L2)

O `SemanticCacheService` atua como um Middleware invisível no Background Job (`InterpretSearchPromptJob`).

### Nível 1: Busca Exata (Hash MD5)
- **Custo:** 0
- **Latência:** < 10ms
- **Como Funciona:** Quando o texto do aluno é **EXATAMENTE** igual (ignorando maiúsculas e espaços) a uma busca já processada, buscamos a *Hash MD5* na tabela `ai_search_cache` através do índice Unique, retornando o filtro instantaneamente.

### Nível 2: Busca por Similaridade (Cosíneo)
- **Custo:** ~$0.0005 por mil buscas (via LLM Embeddings)
- **Latência:** ~200-300ms
- **Como Funciona:**
  1. O texto atual é enviado para o Gemini (`text-embedding-004`).
  2. A IA retorna um vetor de `768 dimensões` que mapeia matematicamente a "intenção" do texto.
  3. O código carrega os vetores mais recentes em cache do MySQL.
  4. Realizamos o *Dot Product* (Produto Escalar) no PHP entre o vetor atual e os cacheados.
  5. **Regra de Aceite:** Se o *Score de Similaridade* for maior que **0.94** (94% de match de intenção), o filtro do cache é clonado e usado sem invocar o modelo completo de chat do Xavier.

## Requisitos
Para esta funcionalidade operar:
1. Deve existir pelo menos uma `ApiKey` cadastrada no Admin.
2. Esta Chave de API deve obrigatoriamente ter a *capability* marcada como **Gerador de Vetores/Embeddings** (Internamente: `CAPABILITY_EMBEDDING`).
3. Somente provedores no formato *Gemini* suportam esta rota por enquanto (v1).

## Observações de Performance (Escala O(N))
Como o MySQL 8 nativo em bancos relacionais carece de buscas vetoriais eficientes tipo HNSW, a busca de *Nível 2* calcula o score varrendo até os 5.000 últimos caches diretamente na memória do PHP. O Kernel do PHP 8+ processa essas multplicações de arrays na velocidade do C, o que atinge performance ótima em memórias dedicadas para essa fila limitada. Se o banco crescer além da necessidade, será necessário rotacionar (`TRUNCATE` de velhos).
