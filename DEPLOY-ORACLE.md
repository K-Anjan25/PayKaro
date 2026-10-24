# Deploying PayKaro on Oracle Cloud — Always Free, step by step

This is the ₹0, always-on, India-hosted route: a small Ubuntu VM in
**Mumbai (ap-mumbai-1)** or **Hyderabad (ap-hyderabad-1)**, Nginx + PHP 8.3,
SQLite, and a free `sslip.io` hostname with a real Let's Encrypt certificate —
no domain purchase.

Budget **45–60 minutes**, most of it waiting for Oracle to create the instance.

| What you get | |
|---|---|
| Cost | ₹0/month, indefinitely |
| Latency from India | ~5–20 ms |
| Cold starts / sleeping | none |
| RAM | 1 GB (AMD micro) or 6 GB (ARM, if capacity allows) |
| Database | SQLite file on the boot volume |
| TLS | Let's Encrypt on `<your-ip>.sslip.io` |
| Backups | nightly `sqlite3 .backup` + a copy off the VM |

Everything else about this app (the `PAYKARO_*` numbers, `APP_KEY`, migrations) is
the same as any other host — see `DEPLOY.md` §3 and §10.

---

## Step 0 · What you need before you start

- An **Oracle Cloud account** (signup needs a debit/credit card for identity
  verification — about a ₹2–8 temporary hold, not a charge). Some signups get
  rejected by fraud scoring; a card from an Indian bank with the address typed
  exactly as on it usually passes.
- **SSH keys on your laptop.** On Linux/macOS:

  ```bash
  ssh-keygen -t ed25519 -C "paykaro" -f ~/.ssh/paykaro
  ```

  That gives you `~/.ssh/paykaro` (private, never share) and `~/.ssh/paykaro.pub`
  (paste this into the Oracle console). On Windows, use PowerShell +
  `ssh-keygen` (built into Windows 10+), or Git Bash.
- The repo merged to **`main`** — the deployment work is in PR #15
  (<https://github.com/K-Anjan25/PayKaro/pull/15>). Merge it, or deploy the
  `arena/7126d4e5-paykaro` branch instead.

---

## Step 1 · Create the account and pick a region

1. <https://signup.cloud.oracle.com> → country **India**, verify email and card.
2. **Home region: `India West (Mumbai)`** or `India South (Hyderabad)`.

> ⚠️ **The home region is permanent.** It cannot be changed after signup. Choose
> the city closest to your users — Mumbai for most of Maharashtra/Gujarat,
> Hyderabad for Telangana/AP. (A second, different region cannot host Always Free
> resources.)
>
> Provisioning usually takes 5–20 minutes; you get an email when it is ready.

---

## Step 2 · Create the VM

Console → **☰ → Compute → Instances → Create instance**.

| Field | Value |
|---|---|
| Name | `paykaro` |
| Compartment | your root compartment |
| **Image** | **Canonical Ubuntu 24.04** (Always Free eligible) |
| **Shape** | *Ampere* → `VM.Standard.A1.Flex`, **1 OCPU / 6 GB** ← preferred. If it says "out of host capacity", use *Specialty and previous generation* → `VM.Standard.E2.1.Micro` (1/8 OCPU, 1 GB) |
| Primary network | create a new VCN, or pick the existing default |
| Assign public IPv4 | **Yes** (needed for inbound) |
| SSH keys | **Paste public keys** → paste the contents of `~/.ssh/paykaro.pub` |
| Boot volume | 50 GB is plenty (200 GB total free) |

Click **Create**, wait for the state to be **Running**, then copy the
**Public IP address** from the instance page. Call it `<IP>` below.

> ARM capacity in Mumbai/Hyderabad is often exhausted. The AMD `E2.1.Micro` shape
> is always available and is enough for this app at small-team traffic — take it
> and move on. Step 3 adds swap, which is what makes 1 GB comfortable.

---

## Step 3 · First login, updates, swap

```bash
ssh -i ~/.ssh/paykaro ubuntu@<IP>
```

Everything below runs **on the VM** unless it says your laptop.

```bash
# 1. Patch and set the workspace timezone (interest and ageing are IST-based)
sudo apt update && sudo DEBIAN_FRONTEND=noninteractive apt -y upgrade
sudo timedatectl set-timezone Asia/Kolkata

# 2. 2 GB of swap — the difference between "composer install" and an OOM kill
#    on a 1 GB machine. Skip on the 6 GB ARM shape.
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h                      # confirm Swap: 2.0Gi

# 3. Security updates without you thinking about them
sudo apt -y install unattended-upgrades
```

---

## Step 4 · Open ports 80 and 443 — **both layers**

This is the step that trips everyone up: Oracle needs the rule in the **cloud**
firewall *and* Ubuntu's own iptables must allow it. Miss either and the browser
just times out.

**4a. Cloud firewall (browser, Oracle console)**

Instances → `paykaro` → *Primary VNIC* → **Subnet** → **Security Lists** →
*Default Security List* → **Add Ingress Rules**, twice:

| Stateless | Source Type | Source CIDR | IP Protocol | Destination Port Range |
|---|---|---|---|---|
| No | CIDR | `0.0.0.0/0` | TCP | `80` |
| No | CIDR | `0.0.0.0/0` | TCP | `443` |

**4b. The instance firewall (on the VM)**

```bash
# Insert at the TOP of the chain. Oracle's Ubuntu images end their INPUT chain
# with a REJECT rule, so appending (or guessing a line number) silently does
# nothing.
sudo iptables -I INPUT 1 -p tcp --dport 80  -j ACCEPT
sudo iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save          # survives reboot
sudo iptables -L INPUT -n --line-numbers | head -6
```

**4c. Prove it from your laptop** (not the VM):

```bash
nc -z -v -w 5 <IP> 80 ; nc -z -v -w 5 <IP> 443
```

Nothing is listening yet, so the *passing* answer is **`Connection refused`** —
that means the packet reached the machine. A **timeout** means a rule is still
missing (usually 4a). Fix it now, before installing anything.

---

## Step 5 · Install the stack

Ubuntu 24.04 ships PHP **8.3**, which is exactly what `composer.json` pins.

```bash
sudo apt -y install nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
                    php8.3-curl php8.3-sqlite3 php8.3-zip php8.3-bcmath \
                    git unzip sqlite3

# Composer 2
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version

php -v && php -m | grep -E 'pdo_sqlite|mbstring|openssl'
```

---

## Step 6 · Put the code on the server

```bash
sudo mkdir -p /var/www && sudo chown ubuntu:ubuntu /var/www
cd /var/www

# Public repository:
git clone https://github.com/K-Anjan25/PayKaro.git paykaro

# Private repository — use a read-only deploy key instead:
#   ssh-keygen -t ed25519 -N "" -C paykaro-deploy -f ~/.ssh/paykaro_deploy
#   cat ~/.ssh/paykaro_deploy.pub      # GitHub → repo → Settings → Deploy keys
#   GIT_SSH_COMMAND="ssh -i ~/.ssh/paykaro_deploy" \
#     git clone git@github.com:K-Anjan25/PayKaro.git paykaro

cd paykaro
git checkout main                     # or arena/7126d4e5-paykaro, if #15 is unmerged
git log --oneline -1

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
```

---

## Step 7 · Configure `.env`

```bash
cp .env.example .env
php artisan key:generate          # writes APP_KEY into .env — keep this file safe
nano .env
```

Set these values (leave the rest as shipped):

```dotenv
APP_NAME=PayKaro
APP_ENV=production
APP_KEY=base64:…                  # already written by key:generate
APP_DEBUG=false
APP_URL=http://<IP>.sslip.io      # becomes https://<IP>.sslip.io in step 10

APP_TIMEZONE=Asia/Kolkata
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=sqlite              # file: database/paykaro.sqlite
SESSION_DRIVER=file               # one server, so a file is fine
SESSION_SECURE_COOKIE=false       # set to true in step 10, after TLS exists
CACHE_STORE=file
QUEUE_CONNECTION=sync

PAYKARO_DEMO=false                # a real book of business is not a demo
PAYKARO_MSME_DUE_DAYS=45
PAYKARO_DEFAULT_TAX_RATE=18
PAYKARO_BANK_RATE=5.5             # RBI Bank Rate ×3, compounded monthly (DEPLOY.md §10)
PAYKARO_INTEREST_MULTIPLIER=3
PAYKARO_FINANCE_READY_SCORE=85

MAIL_MAILER=log                   # invoices/reminders to storage/logs until SMTP is set
```

`TRUSTED_PROXIES` stays **unset** — Nginx terminates TLS itself, so there is no
proxy to trust (see `DEPLOY.md` §7).

---

## Step 8 · Database, permissions, first boot

```bash
cd /var/www/paykaro

# SQLite file (the schema is created by migrate; never run --seed here).
# It must be writable by php-fpm, or migrate fails with "unable to open database".
touch database/paykaro.sqlite
sudo chown www-data:www-data database/paykaro.sqlite

# The web server writes storage/, bootstrap/cache and the database; you keep the code.
sudo chown -R ubuntu:ubuntu /var/www/paykaro
sudo chown -R www-data:www-data storage bootstrap/cache database/paykaro.sqlite

# Every artisan command that writes runs as www-data:
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize      # config + route + view cache
```

To start over later: `sudo -u www-data php artisan migrate:fresh --force`.

---

## Step 9 · Serve it with Nginx

```bash
sudo nano /etc/nginx/sites-available/paykaro
```

Paste:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name <IP>.sslip.io;           # e.g. 140.238.12.34.sslip.io (sslip.io
                                         # resolves any IP-shaped name to that IP)

    root /var/www/paykaro/public;        # only public/ is ever exposed
    index index.php;
    charset utf-8;

    client_max_body_size 8m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }

    # The CSS is committed and not fingerprinted — a long cache would serve last
    # release's stylesheet after a deploy.
    location /assets/ { expires 1h; add_header Cache-Control "public"; }
}
```

```bash
sudo ln -sf /etc/nginx/sites-available/paykaro /etc/nginx/sites-enabled/paykaro
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**Check it:** `curl -sI http://<IP>.sslip.io/login` should return `HTTP/1.1 200`,
and `http://<IP>.sslip.io/up` should return `200` (both work by bare IP too, since
there is only one server block).

> 502 on every page = the PHP socket name is wrong. Check
> `ls /run/php/` and match `php8.3-fpm.sock` to what is actually there.

---

## Step 10 · Real HTTPS on a free hostname

`sslip.io` resolves `<anything>.<ip-with-dashes-or-dots>.sslip.io` to that IP, so
you get a certificate without buying a domain:

```bash
# e.g. for 140.238.12.34 -> 140.238.12.34.sslip.io
sudo apt -y install certbot python3-certbot-nginx
sudo certbot --nginx -d <IP>.sslip.io --agree-tos -m you@example.com \
             --redirect --no-eff-email
```

Certbot rewrites the Nginx block for 443 and adds the HTTP→HTTPS redirect, then
renews automatically (verify with `sudo certbot renew --dry-run`).

Now flip the two settings that depend on TLS:

```bash
cd /var/www/paykaro
nano .env
```

- `APP_URL=https://<IP>.sslip.io`
- `SESSION_SECURE_COOKIE=true`

(One-liner instead: `sed -i 's|^APP_URL=.*|APP_URL=https://<IP>.sslip.io|; s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env`)

```bash
sudo -u www-data php artisan optimize
sudo systemctl reload php8.3-fpm
```

**Verify TLS reached PHP:** open `https://<IP>.sslip.io/login` and view source —
`assets/app.css` and `action` URLs must be `https://…`. If they are `http://`,
add this line inside the `location ~ \.php$` block (Ubuntu usually passes it
already) and reload Nginx:

```nginx
fastcgi_param HTTPS on;
```

---

## Step 11 · Create the real workspace

There is nothing seeded — you create the first business through the app:

1. Open `https://<IP>.sslip.io/signup`.
2. Create the business + owner account (this is the tenant; see
   `DEPLOY.md` §3 — `migrate --seed` is only for the demo).
3. `/buyers/create` → add one customer, then `/invoices/create` → raise one
   invoice. The due date and interest are computed, not typed.
4. Open that invoice → **Claim packet** → print. Confirm the letterhead, GSTIN
   and schedules print as a document (this is the page a forum reads).
5. Optional: **Settings** → fill GSTIN, PAN, Udyam and bank details — the claim
   packet and invoice emails pull from there.

### Optional: actually send invoice email

`MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`. To deliver, use
any SMTP provider (Brevo/Resend free tiers are ~100–300 mails/day):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=invoices@yourdomain
MAIL_FROM_NAME=PayKaro
```

Then `sudo -u www-data php artisan optimize` and send one invoice to yourself.

---

## Step 12 · Backups (do this the same day, not later)

SQLite is one file, which makes this easy and easy to forget.

```bash
mkdir -p /home/ubuntu/backups

# Test it once by hand:
sqlite3 /var/www/paykaro/database/paykaro.sqlite \
  ".backup '/home/ubuntu/backups/paykaro-$(date +%F).sqlite'"
ls -lh /home/ubuntu/backups
```

Then nightly, keeping 14 days:

```bash
crontab -e
```

```cron
15 2 * * * sqlite3 /var/www/paykaro/database/paykaro.sqlite ".backup '/home/ubuntu/backups/paykaro-$(date +\%F).sqlite'" && find /home/ubuntu/backups -name 'paykaro-*.sqlite' -mtime +14 -delete
```

**A backup on the same disk is not a backup.** Copy it off the VM — either pull
it to your laptop occasionally:

```bash
scp -i ~/.ssh/paykaro ubuntu@<IP>:/home/ubuntu/backups/paykaro-$(date +%F).sqlite ~/paykaro-backups/
```

or push it to object storage (Cloudflare R2 / Backblaze B2 free tiers) with
`rclone` on the same cron.

**Restore:** stop PHP-FPM, copy the newest backup over
`database/paykaro.sqlite`, `chown www-data`, start PHP-FPM.

---

## Step 13 · Keep it running — and what "always online" really means

### Your PC has nothing to do with it

Once Steps 1–10 are done, the app is served by an Oracle VM in an Oracle data
centre. It keeps running when your laptop is shut, asleep, on a train, or
reinstalled. Your machine is only needed to:

| Task | Needs your PC? | When |
|---|---|---|
| Serving requests to users | **No** | always — the VM does it |
| Sending invoice email | **No** | the VM (or an SMTP relay) does it |
| Nightly backup | **No** | cron on the VM |
| TLS renewal | **No** | `certbot.timer` on the VM |
| Deploying a code change | Yes | `git push` + the Step 14 commands |
| Restarting after Oracle stops the VM | Yes (or your phone) | only if idle reclamation fires |

The one thing that *would* go offline with your PC is running the app locally
(`php artisan serve`) and calling that "deployed" — a dev server on a laptop is
not a deployment, and there is no tunnel or port-forward in this guide.

### The single caveat: idle reclamation

Always Free instances may be **stopped** by Oracle if, over a rolling 7 days,
95th-percentile **CPU, network and memory** are *all* below 20%. Two things make
that survivable:

- **The boot volume and your database file are not deleted.** Only the instance
  is stopped; starting it again from the console brings the app back with data
  intact. (This is why the nightly backup in Step 12 is still not optional.)
- **A real workspace rarely trips it.** Nginx + PHP-FPM resident, plus a few
  daily visitors, keeps memory above 20% of 1 GB and clears the network bar. A
  brand-new site with zero visitors for a week is the risk case:

  ```cron
  0 8 * * * curl -fsS -m 10 https://<IP>.sslip.io/up > /dev/null
  ```

  Add that with `crontab -e` (Step 12 shows where). It keeps a little traffic
  flowing and, more usefully, tells you nothing has silently stopped.

### Want reclamation off the table?

Two options, in order of effort:

1. **Upgrade the tenancy to Pay-As-You-Go** (Billing → Upgrade). Stay inside the
   Always Free limits and the bill stays ₹0 — Oracle has stated PAYG tenancies
   are exempt from idle reclamation, and ARM capacity is also more available to
   them. The trade: exceeding the limits now **bills you** instead of being
   blocked, so set a **Budget alert** at ₹100 in Billing → Cost Management.
2. **Keep the daily ping cron** and accept a stop-and-restart in the worst case.

### What else can take it down (and the fix)

| Cause | Symptom | Fix |
|---|---|---|
| Idle reclamation | Site unreachable, instance **Stopped** in console | Start it; add the ping cron |
| VM reboot (patching, host maintenance) | Everything down until boot finishes | Nginx and PHP-FPM are enabled at boot — it self-recovers. Verify: `sudo systemctl is-enabled nginx php8.3-fpm` → `enabled` |
| App process died | `502 Bad Gateway` | `sudo systemctl restart php8.3-fpm nginx` |
| Disk full | `500`s, writes fail | `df -h`; prune old backups, `journalctl --vacuum-time=7d` |
| Certificate expiry | Browser `NET::ERR_CERT_DATE_INVALID` | `sudo certbot renew`; the installed timer normally does it |
| Card verification lapses | Whole tenancy suspended | Keep a valid card on the account |

### Prove it, once

Deploy, then **shut your laptop down for an hour** and open the site from your
phone. If it loads, the deployment is independent of your machine — which is the
whole point.

- **Monthly housekeeping** (5 minutes): `sudo apt update && sudo DEBIAN_FRONTEND=noninteractive apt -y upgrade`,
  check `df -h` and `free -h`, confirm `ls /home/ubuntu/backups` is filling up,
  and `sudo certbot renew --dry-run`.
- **Logs:** `sudo tail -f /var/www/paykaro/storage/logs/laravel.log`,
  Nginx at `/var/log/nginx/{access,error}.log`, PHP at
  `sudo journalctl -u php8.3-fpm -n 100`.

---

## Step 14 · Shipping changes later

```bash
cd /var/www/paykaro
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo systemctl reload php8.3-fpm
```

For a minute of downtime instead of a possibly mixed release, wrap it:

```bash
sudo -u www-data php artisan down --secret="$(openssl rand -hex 12)"
# …the four commands above…
sudo -u www-data php artisan up
```

---

## If something breaks

| Symptom | Cause and fix |
|---|---|
| Browser times out on `<IP>` | Port not open. Redo **both** Step 4a (Security List) and 4b (iptables), then `curl` from your laptop |
| `502 Bad Gateway` | PHP socket mismatch or PHP-FPM down: `ls /run/php/`, `sudo systemctl status php8.3-fpm` |
| `403 Forbidden` on every path | `root` is not `/var/www/paykaro/public`, or `try_files` is missing from Step 9 |
| `500` + "No application encryption key" | `php artisan key:generate` never ran, or `.env` was edited after caching: re-run `optimize` |
| 500 + `could not find driver` | Missing `php8.3-sqlite3`: install it, `sudo systemctl restart php8.3-fpm` |
| 500 + "attempt to write a readonly database" | Ownership: `sudo chown -R www-data:www-data storage bootstrap/cache database` |
| Login bounces back to `/login` | `APP_URL` and scheme disagree with the browser, or `SESSION_SECURE_COOKIE=true` before TLS existed (Step 10) |
| CSS missing after an update | `assets/app.css` cached: hard-reload, or lower the `expires` in Step 9 |
| `composer install` killed | Out of memory — Step 3 swap, or run it again with `COMPOSER_MEMORY_LIMIT=-1` |
| certbot: "Failed authorization" | Port 80 blocked (Step 4), or the hostname does not match the IP — it must be `<IP>.sslip.io`, dots included |
| ARM instance won't launch | "Out of host capacity" — use `VM.Standard.E2.1.Micro` (Step 2) |

---

## When you outgrow free hosting

Nothing here needs a rewrite. The database is a single SQLite file, the app is a
plain Laravel tree, and the repo also ships a `Dockerfile` (see `DEPLOY.md` §13)
— so moving to a paid Indian VPS or a PaaS is a copy of `database/paykaro.sqlite`
(or a `migrate` to Postgres) plus a DNS change. `DEPLOY.md` §3 has the production
`.env` for a larger host; §10 has the two settings that bite during a migration
(`APP_KEY`, `PAYKARO_BANK_RATE`).
