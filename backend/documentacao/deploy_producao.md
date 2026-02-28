# Guia de Deploy em Produção (VPS)

Este documento descreve os comandos exatos que devem ser executados no terminal do servidor de produção (VPS) para atualizar o sistema de forma **segura**, sem perder nenhum dado do banco de dados (usuários, planos, etc).

## ⚠️ Sobre o Banco de Dados
**NUNCA**, sob nenhuma circunstância em produção, utilize o comando `docker compose down -v` ou execute `php artisan migrate --seed` num banco já existente com dados reais. Isso apagará permanentemente o volume do banco de dados.

Siga os passos abaixo, em ordem, copiando e colando no terminal da VPS.

---

### Passo 1: Baixar seu código novo do GitHub
```bash
sudo git pull origin main
```

### Passo 2: Parar todos os serviços de produção (mantendo os dados seguros no disco)
```bash
sudo docker compose -f docker-compose.prod.yml down
```

### Passo 3: Reconstruir a imagem Docker e subir em segundo plano
Isso vai construir a imagem já engolindo todo seu código modificado (como a exclusão de arquivos Blade) e vai configurar o sistema para ler os recursos da VPS (via `/host_proc`).
```bash
sudo docker compose -f docker-compose.prod.yml up -d --build
```

### Passo 4: Dar um respiro pro serviço MySQL
*Aguarde pelo menos 10 a 15 segundos antes de passar para o último comando.*
O container MySQL subiu, mas o serviço de banco de dados lá dentro requer alguns segundos para carregar as tabelas e aceitar conexões locais.

### Passo 5: Executar as migrações (se houver novas) SEM apagar dados existentes
```bash
sudo docker compose -f docker-compose.prod.yml exec app php artisan migrate
```

---

## Script Automatizado (Linha Única Segura)

Se você preferir rodar tudo de uma vez e apenas acompanhar os logs terminando, pode utilizar esta linha única no terminal da VPS:

```bash
sudo git pull origin main && \
sudo docker compose -f docker-compose.prod.yml down && \
sudo docker compose -f docker-compose.prod.yml up -d --build && \
echo "Aguardando MySQL subir (15s)..." && sleep 15 && \
sudo docker compose -f docker-compose.prod.yml exec app php artisan migrate
```

> **Feito isso, o ambiente de produção estará totalmente sincronizado com o GitHub, os medidores do painel Monitor do React estarão pegando a CPU e RAM reais da VPS, e seus usuários de produção permanecerão com suas contas e redações perfeitamente intactas!**
