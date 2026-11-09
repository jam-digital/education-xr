#!/bin/sh
# Runs on the server from the GitHub Actions deploy job, in /opt/education-xr (.10 behind edge-caddy; earlier .152 behind the portal Caddy).
# Needs DOMAIN and ADMIN_PASSWORD. Images are built on GitHub (PREBUILT=1): this VPS builds nothing.
set -eu
cd /opt/education-xr

command -v docker >/dev/null 2>&1 || { curl -fsSL https://get.docker.com | sh; }

# .env.app is created once and then kept: it holds APP_KEY and the database password
if [ ! -f .env.app ]; then
  cp .env.docker.example .env.app
  KEY="base64:$(head -c 32 /dev/urandom | base64)"
  DBPW="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|; s|^DB_PASSWORD=.*|DB_PASSWORD=$DBPW|" .env.app
  printf 'DB_PASSWORD=%s\n' "$DBPW" > .env
fi
setenv() { f="$1"; shift; grep -q "^$1=" "$f" && sed -i "s|^$1=.*|$1=$2|" "$f" || echo "$1=$2" >> "$f"; }
setenv .env.app APP_URL "https://$DOMAIN"
[ -n "${ADMIN_PASSWORD:-}" ] && setenv .env.app ADMIN_PASSWORD "$ADMIN_PASSWORD"
if [ -n "${R2_ACCESS_KEY_ID:-}" ] && [ -n "${R2_SECRET_ACCESS_KEY:-}" ] && [ -n "${R2_BUCKET:-}" ]; then
  setenv .env.app BUILDS_DISK r2
  setenv .env.app R2_ACCESS_KEY_ID "$R2_ACCESS_KEY_ID"
  setenv .env.app R2_SECRET_ACCESS_KEY "$R2_SECRET_ACCESS_KEY"
  setenv .env.app R2_BUCKET "$R2_BUCKET"
  setenv .env.app R2_ENDPOINT "${R2_ENDPOINT:-}"
fi

setenv .env.app VR_X_ACCEL 1
[ -n "${LIQPAY_PUBLIC_KEY:-}" ] && setenv .env.app LIQPAY_PUBLIC_KEY "$LIQPAY_PUBLIC_KEY"
[ -n "${LIQPAY_PRIVATE_KEY:-}" ] && setenv .env.app LIQPAY_PRIVATE_KEY "$LIQPAY_PRIVATE_KEY"

# .152: мережу створює музейний портал; .10: її створює edge-caddy (move-to-10.sh), а якщо нема — створюємо самі
docker network inspect mincult_default >/dev/null 2>&1 || docker network create mincult_default >/dev/null

C="docker compose -p education-xr -f docker-compose.yml -f docker-compose.prod.yml"
[ "${PREBUILT:-0}" = 1 ] || COMPOSE_PARALLEL_LIMIT=1 COMPOSE_BAKE=false $C build
$C up -d --no-build --remove-orphans
$C up -d --no-build --force-recreate edu-web   # recreate, not restart: nginx.conf is a single-file bind mount, rsync replaces the inode

mkdir -p /opt/caddy-sites
sed "s|EDU_DOMAIN|$DOMAIN|" deploy/education-xr.caddy > /opt/caddy-sites/education-xr.caddy
# Caddy порталу (.152) або edge-caddy (.10); обидва читають /opt/caddy-sites як /etc/caddy/sites
CADDY="$(docker ps -qf label=com.docker.compose.project=mincult -f label=com.docker.compose.service=caddy | head -1)"
[ -n "$CADDY" ] || CADDY="$(docker ps -qf name=^edge-caddy$ | head -1)"
if [ -n "$CADDY" ] && docker exec "$CADDY" test -f /etc/caddy/sites/education-xr.caddy; then
  # одна невалідна *.caddy зупиняє reload для всіх сайтів сервера: спершу validate, і при помилці прибираємо свій файл
  if docker exec "$CADDY" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile; then
    docker exec "$CADDY" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
  else
    rm -f /opt/caddy-sites/education-xr.caddy
    echo "::error::caddy validate failed, education-xr.caddy removed, other sites untouched"; exit 1
  fi
else
  echo "::warning::No Caddy (portal or edge-caddy) mounts /opt/caddy-sites; the site is not reachable from outside"
fi
docker image prune -f >/dev/null
$C ps

inner() { $C exec -T edu-web wget -qO /dev/null -S "http://127.0.0.1$1" 2>&1 | grep -o 'HTTP/1\.[01] [0-9]*' | tail -1 | awk '{print $2+0}'; }
for i in $(seq 1 24); do
  login="$(inner /admin/login)"; echo "try $i: /admin/login -> $login"
  [ "$login" = 200 ] && break; sleep 10
done
# Livewire 4 serves its script from /livewire-<hash>/livewire.js (served by PHP, not a file in public/)
lwpath="$($C exec -T edu-web wget -qO - http://127.0.0.1/admin/login 2>/dev/null | grep -o 'src="[^"]*/livewire-[^"]*\.js[^"]*"' | head -1 | sed 's/^src="//; s/"$//; s|^https\?://[^/]*||')"
lw="$(inner "${lwpath:-/livewire/livewire.min.js}")"; echo "${lwpath:-/livewire/livewire.min.js} -> $lw"
[ "$lw" = 200 ] || echo "::error::Livewire JS is not served: the login form cannot work"
echo "DNS of $DOMAIN: $(getent hosts "$DOMAIN" | awk '{print $1}' | tr '\n' ' ')(server: $(curl -s --max-time 5 https://api.ipify.org || echo '?'))"
public="$(curl -s --max-time 20 -o /dev/null -w '%{http_code}' "https://$DOMAIN/admin/login" || true)"
echo "https://$DOMAIN/admin/login -> $public"
[ "$public" = 200 ] || echo "::warning::https://$DOMAIN/ is not reachable yet: check the DNS A record and the Caddy log"
[ "$login" = 200 ] || { $C logs --tail=30 edu-app; $C exec -T edu-app sh -c 'tail -c 3000 $(ls -t storage/logs/*.log | head -1)' || true; }
[ "$login" = 200 ]
