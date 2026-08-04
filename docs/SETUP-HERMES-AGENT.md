# SETUP-HERMES-AGENT.md

## Setup Shared Hosting (Laravel)
1. Add variables to `system.aiprenue/.env`:
   ```env
   APP_URL=https://app.aipreneur.co.id
   HERMES_AGENT_TOKEN=your_secure_random_token_here
   HERMES_REPORT_ROLES=owner,it,manager
   APP_TIMEZONE=Asia/Jakarta
   ```
2. Clear configuration cache:
   ```bash
   php artisan optimize:clear
   ```

## Setup VPS Cron & Environment
1. Store same token in VPS environment or `.env` file:
   ```env
   HERMES_AGENT_TOKEN=your_secure_random_token_here
   ```
2. Create cron runner script at `/opt/hermes/run-hermes.sh`:
   ```bash
   #!/usr/bin/env bash
   set -euo pipefail

   APP_URL="https://app.aipreneur.co.id"
   TOKEN="${HERMES_AGENT_TOKEN}"
   DATE="$(date -d 'now' +%F)"

   RESP="$(curl -sS -X POST "${APP_URL}/api/hermes/daily-report" \
     -H "Authorization: Bearer ${TOKEN}" \
     -H "H-Content-Type: application/json" \
     --data "{\"date\":\"${DATE}\"}")"

   echo "$(date '+%F %T') :: ${RESP}"
   ```
3. Set executable permission:
   ```bash
   chmod +x /opt/hermes/run-hermes.sh
   ```
4. Add to crontab via `crontab -e` (to run at 09:00 WIB daily):
   ```cron
   0 9 * * * /bin/bash /opt/hermes/run-hermes.sh >> /var/log/hermes-daily.log 2>&1
   ```

## Verification
```bash
curl -sS -X POST "https://app.aipreneur.co.id/api/hermes/daily-report" \
  -H "Authorization: Bearer $HERMES_AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{}"
```
Successful response returns 201:
```json
{"ok":true,"sent_to":...}
```
