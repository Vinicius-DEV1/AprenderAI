# 🤖 Deploy Automatizado: Sail ➔ Produção

Agora o processo está muito mais simples. Ao subir os containers, o sistema faz tudo sozinho.

---

## 📂 1. Arquivos Necessários na Raiz
Certifique-se de que estes 4 arquivos estão na pasta do projeto na VPS:
1. `Dockerfile.prod`
2. `docker-entrypoint.sh`
3. `nginx.conf`
4. `docker-compose.prod.yml`

---

## 🚀 2. Comandos de Deploy (O Único Passo)

Na VPS, após clonar o projeto e configurar o seu `.env`, basta rodar:

```bash
docker-compose -f docker-compose.prod.yml up -d --build
```

### O que acontece automaticamente agora:
1. **Build da Imagem**: O Docker instala o PHP 8.4.16, Node.js, roda `npm install` e `npm run build` (gerando os assets do Vite).
2. **Ao Subir o Container (Entrypoint)**:
   - Ajusta as permissões de `storage` e `cache`.
   - Roda `php artisan migrate --force`.
   - Roda `php artisan optimize`.
   - Inicia o PHP-FPM.

---

## 🔒 3. Dicas de Manutenção
Se você fizer alterações no código e quiser atualizar na VPS:
```bash
git pull
docker-compose -f docker-compose.prod.yml up -d --build
```

> [!TIP]
> O Nginx já está configurado para servir os arquivos estáticos gerados pelo Vite na pasta `public/build`.
