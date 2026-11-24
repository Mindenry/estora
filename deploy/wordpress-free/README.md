# ESTORA — full WordPress on a free server

The GitHub Pages demo is static: it cannot run PHP or the CRM. This directory runs the **real
WordPress site + ESTORA Core** (back office, inquiries, viewings, database) on a single free VM.

## Which free option fits?

| Option | PHP + MySQL | Custom theme/plugin | Effort | Honest verdict |
| --- | --- | --- | --- | --- |
| **Oracle Cloud Always Free VM** | Yes | Yes | 30–45 min once | Best permanent free option. A small Ubuntu VM runs Docker, WordPress, MySQL and Caddy. |
| **InfinityFree / 000webhost** | Yes | Yes | 30–60 min, FTP/phpMyAdmin | Works for a quick demo, but no SSH/WP-CLI, tight limits and no reliable cron. |
| **WordPress.com free** | N/A | **No** | — | Cannot install this theme or plugin. Not suitable. |
| **Render / Railway free tiers** | Partly | Yes | 1–2 h | Free web services usually have no persistent disk and no free MySQL; the site or uploads get wiped. |
| **Cloudflare Tunnel** | Yes | Yes | 5 min | Full WordPress at a public HTTPS URL **while your PC stays on**. Good for a review call, not a CV link. |
| **GitHub Pages** | No | Static only | Done | The current portfolio demo; no back office, simulated forms. |

Below: the two practical choices.

---

## 1. Oracle Cloud Always Free (recommended, full site)

Oracle's Always Free tier includes Arm Ampere A1 compute (up to 4 OCPU / 24 GB) and 200 GB of
block storage at no cost. It is a real VM with a public IPv4 and full root access.

### 1.1 Create the server

1. Create an Oracle Cloud account and choose a home region.
2. **Compute → Instances → Create instance**
   - Image: **Ubuntu 24.04**
   - Shape: **VM.Standard.A1.Flex** (Ampere). Start with 2 OCPU / 12 GB, or 1 OCPU / 6 GB if
     capacity is unavailable. The AMD `VM.Standard.E2.1.Micro` (1 GB) is too small for MySQL + WP.
   - Boot volume: 50–100 GB.
   - Upload or paste an SSH public key.
3. **Networking → VCN → Security Lists → Default Security List** add ingress:
   - TCP 80 from `0.0.0.0/0`
   - TCP 443 from `0.0.0.0/0`
   - TCP 22 from your own IP
4. Note the public IPv4 address.

### 1.2 Point a free domain at it

Use [DuckDNS](https://www.duckdns.org/) (free) or any domain you own:

- DuckDNS: create `estora-demo.duckdns.org`, set its IP to the VM's public IPv4.
- Own domain: add an **A record** for `estora.example.com` → VM IPv4.

Caddy will request a Let's Encrypt certificate automatically, so the name must resolve before
the last step.

### 1.3 Install Docker and start ESTORA

```bash
ssh ubuntu@YOUR_SERVER_IP

sudo apt update
sudo apt install -y docker.io docker-compose-v2 git
sudo usermod -aG docker "$USER"
newgrp docker

git clone https://github.com/Mindenry/estora.git
cd estora/deploy/wordpress-free

cp .env.example .env
nano .env          # DOMAIN, EMAIL, database and admin passwords

bash setup.sh
```

`setup.sh` starts MySQL and WordPress, installs WordPress at `https://$DOMAIN`, activates the
theme and plugin, runs migrations, seeds the 21 demo listings (when `ESTORA_DEMO=1`) and starts
Caddy.

Open:

- Public site: `https://YOUR_DOMAIN/`
- Thai: `https://YOUR_DOMAIN/?lang=th`
- Back office: `https://YOUR_DOMAIN/estora-admin/`
- WordPress admin: `https://YOUR_DOMAIN/wp-admin/`

Demo staff accounts are created by the seed with the password in
`ESTORA_TEST_AGENT_PASSWORD` / `Estora!Agent!2026`. **Change them before sharing the URL.**

### 1.4 Update and operate

```bash
cd ~/estora
git pull
cd deploy/wordpress-free
bash setup.sh          # idempotent: installs, migrates, restarts Caddy
```

Useful commands:

```bash
docker compose ps
docker compose logs -f caddy
docker compose --profile tools run --rm -T cli plugin list
docker compose --profile tools run --rm -T cli estora seed --force   # reset demo data
```

### 1.5 Backup

```bash
cd ~/estora/deploy/wordpress-free
source .env
mkdir -p ~/backups
docker compose exec -T db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" \
  | gzip > ~/backups/estora-$(date +%F).sql.gz
docker run --rm -v estora_wp_data:/data -v ~/backups:/backup alpine \
  tar czf /backup/uploads-$(date +%F).tar.gz -C /data wp-content/uploads
```

Copy `~/backups` off the VM (Oracle block storage is not a backup).

### 1.6 Limits to know

- Ampere capacity is not available in every region at every moment; retry or pick another region.
- Oracle may reclaim idle Always Free resources. A site with real traffic is fine; a forgotten VM
  can be reclaimed. Keep the instance active and check the console occasionally.
- One VM means no managed database failover. Take backups.
- Outbound port 25 is blocked. For reliable email, use an SMTP plugin/provider (Brevo, Resend,
  Postmark) instead of `wp_mail`.
- Keep `WP_DEBUG` off (the kit already does) and change all demo passwords.

---

## 2. InfinityFree / free shared hosting (quick, limited)

Free shared hosts give PHP + MySQL but no SSH. Use this only if a VM is not possible.

1. Create an account and a free subdomain.
2. Use their **WordPress installer** (Softaculous) or upload WordPress.
3. Zip and upload:
   - `wp-content/themes/estora-theme`
   - `wp-content/plugins/estora-core`
4. Export the local database and import it with phpMyAdmin:
   ```bash
   docker compose exec -T db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" estora > estora.sql
   ```
   Edit the `wp_options` `home`/`siteurl` rows to the new URL, then import.
5. Copy `wp-content/uploads` over FTP.
6. Activate **ESTORA** and **ESTORA Core**, then save permalinks once.

Limits: no WP-CLI/cron, low entry-process limits, no mail, and free hosts can suspend busy sites.
Not recommended as the long-term portfolio link.

---

## 3. Full WordPress from your own PC (temporary public URL)

If you only need a live URL for a call or an interview:

```bash
# terminal 1
docker compose up -d

# terminal 2 (install cloudflared first)
cloudflared tunnel --url http://localhost:8080
```

Cloudflare prints a `https://….trycloudflare.com` URL that serves the full WordPress site. It
disappears when the tunnel or your PC stops. Use this for a demo, not for a CV link.

---

## What "free" really means

- The GitHub Pages demo is free and permanent, but static.
- Oracle Always Free is free indefinitely within the limits above; it is the only option here that
  runs the actual PHP application and database with a stable public URL.
- Shared free hosting is free but fragile.
- A paid VPS (€5/month) is still the simplest long-term option if the demo gets real traffic.

The repository's CI covers lint, unit, integration, HTTP and browser tests; it does not attempt to
provision cloud infrastructure.
