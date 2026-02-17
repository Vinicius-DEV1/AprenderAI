# Relatório Técnico: Integração Dev ENEM API

## 1. Mapeamento de Schema (De-Para)

Abaixo o mapeamento entre os campos da **API Dev ENEM** (baseado no endpoint `/exams/{year}/questions`) e a tabela `questions` do banco de dados.

| Coluna no Banco (`questions`) | Campo na API (JSON) | Tipo de Dados (Banco) | Tipo de Dados (API) | Observações |
| :--- | :--- | :--- | :--- | :--- |
| `statement` | `context` + `alternativesIntroduction` | `TEXT` | `String` | Concatenação necessária. Fallback para `title` se vazio. |
| `alternatives` | `alternatives` (lista) | `JSON` | `Array of Objects` | Transformação necessária de `[{letter, text, ...}]` para `{"A": "texto", ...}`. |
| `correct_answer` | `correctAlternative` OU `isCorrect` | `CHAR(1)` | `String` / `Boolean` | Extraído de propriedade direta ou flag dentro de `alternatives`. |
| `year` | (Parâmetro da URL) | `INTEGER` | - | O ano é definido pelo loop de requisição, não necessariamente vem no objeto da questão. |
| `subject` | `discipline` | `ENUM` | `String` | Lógica de inferência necessária (Filtragem 'Matemática' / 'Português'). |
| `topic` | `discipline`? | `STRING` | `String` | **Gap:** A API fornece disciplinas específicas (ex: "História"), mas nosso foco é filtrar. Pode ser usado para granularidade além de 'Matemática'/'Português'. |
| `theme` | - | `STRING` | - | **Sem correspondência.** |
| `difficulty` | - | `ENUM` | - | **Sem correspondência.** Default: 'medium'. |
| `explanation` | - | `TEXT` | - | **Sem correspondência.** |
| `source` | - | `ENUM` | - | Definido fixo como `'manual'` (ou novo status recomendado `'api_import'`). |
| `origin` | - | `STRING` | - | Recomendado preencher com "ENEM {ano}". |

## 2. Campos Obrigatórios vs. Ausentes na API

| Campo Obrigatório (BD) | Status na API | Ação Recomendada |
| :--- | :--- | :--- |
| `difficulty` | **Inexistente** | Manter default `'medium'` ou implementar lógica heurística baseada no tamanho/complexidade (não recomendado sem dados reais). <br> **Ideal:** Criar rotina posterior de calibração via uso dos alunos. |
| `theme` | **Inexistente** | Deixar `NULL`. A API retorna `discipline`, que usamos para o filtro macro (`subject`). |
| `explanation` | **Inexistente** | Deixar `NULL` ou gerar via IA posteriormente. |

## 3. Análise de Filtros e Tipagem

### Filtro de Disciplinas
A lógica atual implementada no `ImportEnemCommand.php` é:
1.  **Matemática:** Busca string "matematica" ou "matemática" no campo `discipline`.
2.  **Linguagens (Português):** Busca "linguagens" ou "portugues".
    *   **Exclusão:** Remove se detectar "ingles", "espanhol" (Espanhol/Inglês) no campo `language` ou `discipline`.

**Avaliação:** A lógica é robusta para a estrutura conhecida do ENEM.
*   **Recomendação:** Adicionar normalização de acentos (`Str::slug` ou similar) para evitar falhas com encodings diferentes.

### Compatibilidade de Tipos
*   **`statement` (TEXT):** O ENEM possui textos de apoio longos. `TEXT` (64KB) geralmente é suficiente, mas textos muito longos + enunciado podem exceder.
    *   **Recomendação:** Monitorar erros de truncamento. Se necessário, alterar para `MEDIUMTEXT`.
*   **`alternatives` (JSON):** O banco já suporta JSON nativo. A API entrega array estruturado, a conversão é trivial e sem perda de dados.

## 4. Plano de Implementação (Draft) da Ingestão

### Tratamento de Imagens
O código atual **ignora imagens**. Muitas questões de Matemática e Física dependem de figuras.
*   **Estratégia:**
    1.  Verificar se a API retorna array de `files` ou links de imagens no corpo do texto (Markdown/HTML).
    2.  Se houver URLs externas:
        *   **Opção A (Simples):** Salvar URL direto no texto (risco de link rot).
        *   **Opção B (Robusta):** Baixar imagem, salvar no Storage (S3/Local) e substituir URL no `statement`.

### Deduplicação
A lógica atual verifica `statement` exato + `year`.
*   **Risco:** Pequenas variações de espaçamento ou quebra de linha na API podem duplicar questões.
*   **Melhoria:** Gerar um *hash* do texto normalizado (sem espaços extras, lowercase) para comparação.
    `md5(Str::slug($statement))`

### Fluxo de Carga
1.  Iterar Anos (2009-2023).
2.  Paginar API (backoff em caso de 429 Too Many Requests).
3.  Processar cada questão:
    *   Filtrar Mat/Port.
    *   Normalizar Texto.
    *   Transformar Alternativas.
    *   Baixar/Tratar Imagens (Se disponível).
    *   Verificar Duplicidade (Hash).
    *   Insert.

## 5. Diagnóstico de Gap (Alterações no Banco)

Não são necessárias alterações estruturais críticas (breaking changes), mas recomenda-se:

1.  **Coluna `external_id` (Opcional mas Útil):**
    *   Adicionar coluna `external_id` na tabela `questions` para armazenar o ID original da questão na API (se fornecido). Facilita atualizações futuras e evita duplicação de forma mais segura que comparação de texto.

2.  **Alteração de Tipo (Preventiva):**
    *   Avaliar mudar `statement` para `MEDIUMTEXT` se houver histórico de textos > 64KB.

## 6. Próximos Passos (Recomendações)

1.  **Refinar Script de Importação:** Atualizar `ImportEnemCommand.php` para preencher o campo `topic` (com o valor cru de `discipline`) e `origin` (com "ENEM {ano}").
2.  **Investigar Imagens:** Rodar um teste manual na API para confirmar como imagens são entregues. Se forem URLs, implementar o downloader.
3.  **Gerar Explicações:** Criar um Job em background para pegar questões recém-importadas (sem explicação) e consultar a IA para gerar a resolução comentada.
4.  **Calibração de Dificuldade:** Como a API não fornece, iniciar todas como 'medium' e criar algoritmo que ajusta baseada na taxa de erro dos alunos nos simulados.
