# Integração API Dev ENEM - Guia Técnico

Este documento descreve o processo de integração, a estrutura de dados e como executar a importação das questões do ENEM.

## 1. Visão Geral

O sistema consome a API [enem.dev](https://enem.dev) para popular o banco de dados com questões oficiais do ENEM de 2009 a 2023.

### Principais Características da Importação
*   **Filtro Estrito:** Apenas questões de **Matemática** e **Linguagens (Português)** são importadas. Inglês e Espanhol são explicitamente removidos.
*   **Imagens:** Imagens presentes nos enunciados são detectadas, baixadas para o servidor local (`storage/app/public/questions/images/{ano}`) e os links no texto são substituídos para apontar para o arquivo local.
*   **Deduplicação:** Utiliza o ID original da API (`external_id`) ou, se ausente, uma comparação do texto do enunciado + ano.

## 2. Estrutura do Banco de Dados

A tabela `questions` foi adaptada com as seguintes alterações:

| Coluna | Tipo | Descrição |
| :--- | :--- | :--- |
| `external_id` | `VARCHAR` | ID original da questão na API (para controle de duplicidade). |
| `statement` | `MEDIUMTEXT` | Enunciado da questão (suporta textos longos e HTML). |
| `topic` | `VARCHAR` | Armazena a disciplina original da API (ex: "matematica", "linguagens"). |
| `origin` | `VARCHAR` | Identificador de origem, ex: "ENEM 2023". |

## 3. Como Executar a Importação

### Pré-requisitos
*   Conexão com a internet.
*   PHP 8.2+ configurado.
*   Permissões de escrita na pasta `storage`.

### Comando Manual
Para importar os dados, execute o comando Artisan:

```bash
php artisan enem:import --from=2009 --to=2023
```

**Parâmetros Opcionais:**
*   `--from=ANO`: Ano inicial (padrão: 2009).
*   `--to=ANO`: Ano final (padrão: 2023).

### Exemplo de Saída
```text
Iniciando importação ENEM de 2009 a 2023...
Processando ano 2009...
Ano 2009 - Página 1 processada (25 itens).
...
RELATÓRIO FINAL DE IMPORTAÇÃO (ENEM)
Total Geral Inserido: 1200
```

## 4. Reset do Banco de Dados (Cuidado!)

Para reiniciar todo o banco e reimportar do zero (apagando usuários, simulados, etc):

```bash
php artisan migrate:fresh --seed
php artisan enem:import
```

## 5. Troubleshooting

*   **Imagens não aparecem:** Execute `php artisan storage:link` para criar o link simbólico da pasta `public` para `storage`.
*   **Erro 429 (Rate Limit):** O script já possui um mecanismo de espera automática (backoff), mas se persistir, aguarde alguns minutos antes de tentar novamente.
