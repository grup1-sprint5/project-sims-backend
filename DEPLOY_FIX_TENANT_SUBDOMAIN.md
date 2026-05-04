# Deploy Tenant Subdomain Registration (fix/tenant-subdomain)

Instruccions completes per desplegar la funcionalitat de registre de tenants al VPS.

## Prerequisits
- Wildcard DNS configurat: `*.jordiarnau...` apunta a la IP del VPS
- Apache/Nginx amb vhost per acceptar subdominis
- Base de dades PostgreSQL accessible
- Git acces al repositori

---

## Passos de Deploy

### 1. Backend Deploy

```bash
# SSH al servidor
ssh user@vps-ip

# Navega al directori del backend
cd /var/www/sims-back

# Fes pull de la branca fix/tenant-subdomain
git fetch origin
git checkout fix/tenant-subdomain
# O si vols fusionar a develop primer:
# git checkout develop && git merge fix/tenant-subdomain

# Instal·la dependències (si cal)
composer install --no-dev --prefer-dist

# Executa les migracions per crear taula tenant_requests
php artisan migrate --force

# Neteja i reconstrueix caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reinicia php-fpm o el servei web
sudo systemctl reload php8.2-fpm  # Ajusta la versió de PHP
sudo systemctl reload nginx       # O apache2
```

### 2. Frontend Deploy

```bash
# SSH al servidor (nova terminal o torna al VPS)
ssh user@vps-ip

# Navega al frontend
cd /var/www/sims-front

# Fes pull de la branca fix/tenant-subdomain
git fetch origin
git checkout fix/tenant-subdomain
# O si vols fusionar a develop:
# git checkout develop && git merge fix/tenant-subdomain

# Instal·la dependències
npm install

# Build per a producció
npm run build

# Si estàs usant Nginx/Apache, serveix la carpeta /dist
# Copia els estàtics a la carpeta correcta (si cal)
# sudo cp -r dist/* /var/www/sims-front/public/
# O actualitza la configuració del server per apuntar a dist/

# Reinicia Nginx / Apache
sudo systemctl reload nginx
# O
sudo systemctl reload apache2
```

### 3. Configuració .env (Backend)

Assegura't que al `.env` del backend estan configuraats:

```env
# Existents
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=project_sims
DB_USERNAME=...
DB_PASSWORD=...

# Per notificacions al registre (opcional pero recomanat):
SUPERADMIN_EMAIL=admin@jordiarnau...com
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  # O el teu mail provider
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@jordiarnau...com
```

### 4. Comprova que tot funciona

#### Testa l'API públic (registre):

```bash
curl -X POST http://vps-ip/api/register-company \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Company",
    "slug": "test-company",
    "email": "contact@test-company.example.com",
    "notes": "Testing"
  }'
```

Hauríes de rebre una resposta `201 Created`:
```json
{
  "message": "Request received",
  "data": { "id": 1, "name": "Test Company", "slug": "test-company", ... }
}
```

#### Testa el check de slug:

```bash
curl "http://vps-ip/api/tenant-slugs/check?slug=test-company"
```

Hauríes de rebre:
```json
{
  "available": false,
  "message": "Slug already requested and pending"
}
```

#### Accés al formulari públic de registre:

Obriu al navegador: `http://jordiarnau...com/register-company`

Hauríeu de veure el formulari sense errors. Comproveu:
- Introduïu un slug → veureu validació en viu (checking → available/unavailable)
- Enviar → li donarà un missatge d'èxit si tot va bé

#### Accés al panell super-admin de peticions:

Logueu-vos com superadmin → `http://jordiarnau...com/admin/tenant-requests`

Hauríeu de veure les peticions de registre pendent i poder clicar "Approve" per crear el tenant automàticament.

---

## Rollback (si cal desfer-se)

Si trobes problemes, pots tornar a la branca `develop`:

```bash
# Backend
cd /var/www/sims-back
git checkout develop
php artisan migrate --force  # Torna a les migracions de develop
sudo systemctl reload php8.2-fpm

# Frontend
cd /var/www/sims-front
git checkout develop
npm install && npm run build
sudo systemctl reload nginx
```

---

## Notes Importants

1. **Wildcard DNS**: Assegura't que `*.jordiarnau...com` apunta a la IP del VPS. Sense això, els subdominis dels tenants no funcionaran.

2. **Certificat SSL Wildcard**: Si vols HTTPS per als subdominis, necessites un certificat wildcard (Let's Encrypt amb DNS challenge) o automàtric amb Caddy/Traefik.

3. **Nginx/Apache Config**: El vhost ha de tenir `server_name .jordiarnau...com;` (nginx) o `ServerName *.jordiarnau...com` (apache) per acceptar tots els subdominis.

4. **Email**: Si `SUPERADMIN_EMAIL` no està configurat, el sistema no intentarà enviar emails (no petarà, simplement saltarà aquest pas).

5. **Migracions**: Les migracions són idempotents — es poden executar múltiples vegades sense problema si ja existeixen les taules.

6. **Permisos**: Assegura't que l'usuari de deploy (www-data, nginx, php-fpm, etc.) té lectura/escriptura als directoris necessaris.

---

## Monitorització Post-Deploy

- Comprova logs: `tail -f /var/log/syslog` o `php artisan logs`
- Comprova BD: `SELECT * FROM tenant_requests;` hauria de tenir les peticions registrades
- Comprova stats Tenants: `php artisan tenants:list` hauria d'incloure els nous tenants aprovats

---

## Suport

Si trobeu errors o dubtes durant el deploy, consulteu els logs:
- Backend: `/storage/logs/laravel.log`
- Nginx: `/var/log/nginx/error.log`
- PostgreSQL: `sudo journalctl -u postgresql`

