# 🏁 Guia de Migração para Produção (V2 - Recomendado)

Este guia documenta a nova estrutura **Nginx + PHP-FPM (8.4.16)**, configurada para ser 100% automatizada e resiliente.

### 🏗️ O que foi configurado:
1.  **Imagens Otimizadas**: PHP 8.4.16 com as extensões necessárias (GD, Zip, Mysql, etc).
2.  **Asset Build**: O Docker agora processa o `npm install` e `npm run build` durante a criação da imagem.
3.  **Entrypoint Inteligente**: Ao subir, o container ajusta permissões, roda migrações e alimenta o banco (`db:seed`) automaticamente.
4.  **Resiliência**: 
    - A página inicial possui travas para não dar erro 500 se o banco estiver vazio.
    - O `QuestionSeeder` ignora dependências de desenvolvimento (Faker) se elas não estiverem presentes.

---

### 🔥 Comandos para a VPS (Atualizar Domínio)
```bash
cd /var/www/laravel
sudo git pull

# 1. Atualize o APP_URL no .env da VPS (Manual)
# Mude para APP_URL=https://aprenderai.com.br
nano .env 

# 2. Reinicie o Docker
sudo docker compose -f docker-compose.prod.yml up -d --build
```

### 🔒 Configuração de SSL (HTTPS)
Para o domínio funcionar com segurança, rode estes comandos na VPS:

```bash
# 1. Instalar Certbot
sudo apt-get update
sudo apt-get install -y certbot

# 2. Gerar Certificado (Certifique-se que o Docker está UP antes)
# Este comando solicita o certificado via HTTP-01 challenge
sudo certbot certonly --webroot -w /var/www/public -d aprenderai.com.br -d www.aprenderai.com.br
```

> [!IMPORTANT]
> **Portas AWS**: Verifique se as portas **80 (HTTP)** e **443 (HTTPS)** estão abertas no **Security Group** da sua instância EC2 na AWS. Sem isso, o Certbot vai falhar.

### 🛠️ Solução de Problemas
- **Banco de Dados Limpo**: Se você quiser resetar e testar tudo do zero, rode `sudo docker-compose -f docker-compose.prod.yml down -v` antes do `up`.
- **Logs de Automação**: Acompanhe o que o Laravel está fazendo ao subir com `sudo docker-compose -f docker-compose.prod.yml logs -f app`.

---
**Status**: Pronto para Produção 🚀
