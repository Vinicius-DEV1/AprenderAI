#!/bin/bash

# Configuration
domains=(aprenderai.com.br www.aprenderai.com.br)
rsa_key_size=4096
data_path="./certbot"
email="contato@aprenderai.com.br" # Substitua pelo seu email
staging=0 # Defina como 1 para testar sem atingir os limites do Let's Encrypt

if [ -d "$data_path" ]; then
  read -p "Já existem dados de certificados em $data_path. Deseja continuar e sobrescrever? (y/N) " decision
  if [ "$decision" != "Y" ] && [ "$decision" != "y" ]; then
    exit
  fi
fi

# Determine docker-compose command
if docker compose version >/dev/null 2>&1; then
  docker_cmd="docker compose"
else
  docker_cmd="docker-compose"
fi

echo "### Using command: $docker_cmd"

if [ ! -e "$data_path/conf/options-ssl-nginx.conf" ] || [ ! -e "$data_path/conf/ssl-dhparams.pem" ]; then
  echo "### Downloading recommended TLS parameters ..."
  mkdir -p "$data_path/conf"
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf > "$data_path/conf/options-ssl-nginx.conf"
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem > "$data_path/conf/ssl-dhparams.pem"
  echo
fi

echo "### Creating dummy certificate for $domains ..."
path="/etc/letsencrypt/live/$domains"
mkdir -p "$data_path/conf/live/$domains"
$docker_cmd -f docker-compose.prod.yml run --rm --entrypoint "\
  openssl req -x509 -nodes -newkey rsa:1024 -days 1\
    -keyout '$path/privkey.pem' \
    -out '$path/fullchain.pem' \
    -subj '/CN=localhost'" certbot
echo

echo "### Starting nginx ..."
$docker_cmd -f docker-compose.prod.yml up --force-recreate -d webserver
echo

echo "### Deleting dummy certificate for $domains ..."
$docker_cmd -f docker-compose.prod.yml run --rm --entrypoint "\
  rm -rf /etc/letsencrypt/live/$domains && \
  rm -rf /etc/letsencrypt/archive/$domains && \
  rm -rf /etc/letsencrypt/renewal/$domains.conf" certbot
echo

echo "### Requesting Let's Encrypt certificate for $domains ..."
# Join $domains to -d domain1 -d domain2 ...
domain_args=""
for domain in "${domains[@]}"; do
  domain_args="$domain_args -d $domain"
done

# Select appropriate email arg
case "$email" in
  "") email_arg="--register-unsafely-without-email" ;;
  *) email_arg="--email $email" ;;
esac

# Enable staging mode if needed
if [ $staging != "0" ]; then staging_arg="--staging"; fi

$docker_cmd -f docker-compose.prod.yml run --rm --entrypoint "\
  certbot certonly --webroot -w /var/www/certbot \
    $staging_arg \
    $email_arg \
    $domain_args \
    --rsa-key-size $rsa_key_size \
    --agree-tos \
    --no-eff-email \
    --non-interactive \
    --force-renewal" certbot
echo

echo "### Reloading nginx ..."
$docker_cmd -f docker-compose.prod.yml exec webserver nginx -s reload

