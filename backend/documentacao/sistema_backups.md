# Sistema de Backup MySQL → Amazon S3 (Gerenciável pelo Painel)

Este documento detalha a arquitetura, componentes e operação do sistema de backup implementado no **AprovadoAI**.

---

## 1. Visão Geral da Arquitetura

O sistema foi desenhado para ser eficiente em recursos, seguro e totalmente auditável via Painel Admin.

### Fluxo de Dados (Streaming Direto)
Diferente de sistemas tradicionais que salvam o arquivo em disco antes de subir, este sistema utiliza um **pipeline de streaming**:

1. **Extração**: O comando `mysqldump` é executado com `--single-transaction`.
2. **Compressão**: O output do `mysqldump` é enviado via `pipe` para o `gzip`.
3. **Upload**: O stream comprimido é enviado diretamente para o **Amazon S3** via **Multipart Upload**.

**Vantagem**: O servidor EC2 nunca salva o backup no disco físico, evitando problemas de espaço em disco e I/O lento em bancos grandes.

---

## 2. Componentes Técnicos

### Backend (Laravel 11)

- **`DatabaseBackupJob.php`**: Orquestra o backup. Usa `proc_open` para gerenciar os processos de extração e compressão.
- **`BackupService.php`**: Encapsula a lógica do AWS SDK. Gerencia o upload em partes (5MB cada) e gera URLs pré-assinadas para download.
- **`BackupController.php`**: Interface de API para o frontend.
- **`BackupJob.php` (Model)**: Registra cada tentativa, status, tamanho do arquivo e tempo de execução.
- **`config/backup.php`**: Define os defaults do sistema.

### Infraestrutura (Docker & AWS)

- **`Dockerfile.prod`**: Inclui `default-mysql-client` para garantir a disponibilidade do `mysqldump`.
- **`routes/console.php`**: Integra o backup ao Scheduler do Laravel.
- **IAM Role**: O sistema utiliza **IAM Roles for EC2 Instances**, eliminando a necessidade de salvar Access Keys no `.env`.

---

## 3. Segurança

- **Consistência**: O uso de `--single-transaction` garante que o backup seja consistente para tabelas InnoDB sem travar as tabelas de produção.
- **Criptografia**: Recomendado ativar *Default Encryption* (AES-256) no Bucket S3 de destino.
- **Acesso**: Apenas usuários com a role `is.admin` podem visualizar, configurar ou baixar backups.
- **Presigned URLs**: Os links de download expiram automaticamente após 5 minutos.

---

## 4. Configuração e Operação

### Configurações no Painel
O Admin pode configurar via interface:
- **Bucket S3**: Nome do bucket de destino.
- **Prefixo**: Pasta dentro do bucket (ex: `backups/`).
- **Agendamento**: Ativar/Desativar backup automático e definir o horário (UTC).

### Monitoramento
Cada backup gera um registro na tabela `backup_jobs`:
- **Pending/Running**: Processo em andamento.
- **Completed**: Sucesso (mostra tamanho e link de download).
- **Failed**: Falha (registra a mensagem de erro do `stderr` do mysqldump ou da AWS).

---

## 5. Guia de Deploy

1. **Permissões S3**: A instância EC2 deve ter uma IAM Role com permissões de `s3:PutObject` e `s3:GetObject` no bucket alvo.
2. **Dependências**: Rodar `composer install` para instalar o `aws/aws-sdk-php`.
3. **Imagem**: Realizar o rebuild da imagem Docker para incluir o `mysql-client`.
4. **Migration**: Executar `php artisan migrate` para criar a tabela de histórico.

---

## 6. Comandos Úteis (CLI)

Manual via Artisan (se necessário):
```bash
# O backup é disparado via Job, mas pode ser testado via Tinker:
$jobId = \App\Models\BackupJob::create(['status' => 'pending'])->id;
\App\Jobs\DatabaseBackupJob::dispatch($jobId);
```

Logs de execução:
```bash
tail -f storage/logs/laravel.log | grep -i "Backup"
```
