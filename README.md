# KSG Weekly Status Reporting System

A web-based PHP application for staff across all Kenya School of Government campuses to submit, manage, and review weekly departmental activity reports.

---

## Table of Contents

1. [Tech Stack](#tech-stack)
2. [Directory Structure](#directory-structure)
3. [User Roles](#user-roles)
4. [Report Reference Codes](#report-reference-codes)
5. [Docker Deployment (Recommended)](#docker-deployment-recommended)
6. [Deploying Updates](#deploying-updates)
7. [Email Configuration](#email-configuration)
8. [Adding a Domain & SSL](#adding-a-domain--ssl)
9. [Database Backups](#database-backups)
10. [Security Checklist](#security-checklist)
11. [Manual Deployment (Without Docker)](#manual-deployment-without-docker)
12. [Support](#support)

---

## Tech Stack

| Component  | Technology                   |
|------------|------------------------------|
| Language   | PHP 8.2                      |
| Database   | PostgreSQL 17                |
| Web Server | Apache 2.4 (via Docker)      |
| Email      | PHPMailer 6.8 — SMTP/Gmail   |
| Deps       | Composer (PSR-4 autoloading) |
| Container  | Docker + Docker Compose      |
| CI         | GitHub Actions               |

---

## Directory Structure

```
ksg_reporting/
├── .github/workflows/   # GitHub Actions CI pipeline
├── config/              # Database config and app constants
├── cron/                # Scheduled task scripts
├── database/            # schema.sql (auto-applied on first Docker boot)
├── docker/
│   ├── apache/          # Apache VirtualHost config for the app container
│   ├── cron/            # Dockerfile + crontab for the cron container
│   └── db/              # init.sh — seeds the database on first boot
├── public/              # Web root (Apache DocumentRoot)
│   ├── assets/          # CSS, JS, images
│   ├── auth/            # Login / logout controllers
│   ├── reports/         # Report submission and viewing
│   ├── tasks/           # Task management
│   ├── users/           # User management
│   ├── directors/       # Director management
│   ├── account/         # Profile pages
│   └── system/          # Email logs and admin tools
├── scripts/             # CLI utilities (create_user.php)
├── src/                 # Core classes: Auth, Database, Mailer, Report, Task
├── templates/           # Shared HTML templates
├── .env.example         # Environment variable template (safe to commit)
├── .env                 # Live environment config (never commit)
├── docker-compose.yml   # Defines app, cron, and db services
└── Dockerfile           # Builds the PHP 8.2 + Apache app image
```

---

## User Roles

| Role     | Permissions                                                              |
|----------|--------------------------------------------------------------------------|
| Staff    | Submit reports. View only their own submitted reports.                   |
| HoD      | Submit reports. View all reports for their campus and department.        |
| Director | View all reports for their campus across all departments. Cannot submit. |
| Admin    | Full access. View all reports. Create and manage users and directors.    |

---

## Report Reference Codes

Each report is automatically assigned a unique reference code based on campus:

| Campus         | Code Format   |
|----------------|---------------|
| Nairobi (Main) | KSG/01/NBO/XX |
| Mombasa        | KSG/01/MSA/XX |
| Matuga         | KSG/01/MTG/XX |
| Embu           | KSG/01/EBU/XX |
| Baringo        | KSG/01/BRG/XX |

`XX` is a sequential two-digit number auto-incremented per campus.

---

## Docker Deployment (Recommended)

### Prerequisites

- Ubuntu 22.04 LTS VPS (1 GB RAM recommended)
- Git installed (`apt install -y git`)
- Docker Engine + Docker Compose plugin

### 1 — Install Docker

```bash
apt-get update
apt-get install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  tee /etc/apt/sources.list.d/docker.list > /dev/null

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
docker --version && docker compose version
```

### 2 — Create a Deploy User

```bash
useradd -m -s /bin/bash deploy
usermod -aG sudo,docker deploy
passwd deploy
```

### 3 — Set Up Firewall

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

### 4 — Clone the Repository

```bash
su - deploy
git clone https://github.com/Langat18/ksg_reporting.git /var/www/ksg
cd /var/www/ksg
```

### 5 — Configure Environment

```bash
cp .env.example .env
nano .env
chmod 600 .env
```

| Variable        | Description                                    |
|-----------------|------------------------------------------------|
| `DB_PASS`       | Strong password for the PostgreSQL user        |
| `APP_URL`       | `http://<server-ip>` or your domain            |
| `MAIL_ENABLED`  | Set to `true` to activate email notifications  |
| `MAIL_USERNAME` | Gmail address used as sender                   |
| `MAIL_PASSWORD` | Gmail App Password (not your account password) |

> **Never commit `.env` to Git.** It is already listed in `.gitignore`.

### 6 — Build and Start

```bash
docker compose up -d --build
```

On first boot Docker will pull images, install Composer dependencies, and automatically seed the database from `database/schema.sql`.

```bash
docker compose ps   # all three services should show "Up"
```

### 7 — First Login

Visit `http://<your-server-ip>` in a browser.

| Field    | Value           |
|----------|-----------------|
| Email    | admin@ksg.ac.ke |
| Password | password        |

**Change the default password immediately after first login.**

---

## Deploying Updates

```bash
ssh deploy@<server-ip>
cd /var/www/ksg
git pull origin main
docker compose up -d --build
```

Database data is persisted in the Docker volume `postgres_data` and is never affected by rebuilds.

---

## Email Configuration

1. Enable 2-Factor Authentication on the Gmail account.
2. Generate an **App Password** at `myaccount.google.com/apppasswords`.
3. In `.env` set:
   ```
   MAIL_ENABLED=true
   MAIL_USERNAME=noreply@yourdomain.com
   MAIL_PASSWORD=your_16_char_app_password
   ```
4. `docker compose restart`

Cron jobs inside `ksg_cron`:
- **Every minute** — flushes the email queue (`cron/process_email_queue.php`)
- **Daily at 07:00 EAT** — flags overdue tasks (`cron/overdue_tasks.php`)

---

## Adding a Domain & SSL

```bash
# Update .env
APP_URL=https://reports.yourdomain.com

# Stop containers, get certificate
docker compose down
apt install -y certbot
certbot certonly --standalone -d reports.yourdomain.com
docker compose up -d --build
```

Then add an HTTPS VirtualHost to `docker/apache/ksg.conf` and mount the certificate in `docker-compose.yml`.

---

## Database Backups

**Manual:**
```bash
docker exec ksg_db pg_dump -U ksg_user ksg_reports > backup_$(date +%Y%m%d).sql
```

**Automated daily** (deploy user crontab):
```
0 2 * * * docker exec ksg_db pg_dump -U ksg_user ksg_reports > /var/backups/ksg_$(date +\%Y\%m\%d).sql
```

**Restore:**
```bash
docker exec -i ksg_db psql -U ksg_user ksg_reports < backup_20260312.sql
```

---

## Security Checklist

- [ ] `.env` not committed to Git, `chmod 600`
- [ ] Default admin password changed after first login
- [ ] Strong `DB_PASS` (16+ characters)
- [ ] UFW enabled — only ports 22, 80, 443 open
- [ ] HTTPS configured with valid SSL certificate
- [ ] SSH root login disabled after deploy user confirmed:
  ```bash
  sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
  systemctl restart sshd
  ```
- [ ] Regular database backups scheduled

---

## Manual Deployment (Without Docker)

> Only if Docker is unavailable. Docker is the recommended method.

**Requirements:** PHP 8.2 (`pdo_pgsql`, `mbstring`, `xml`, `openssl`) · PostgreSQL 14+ · Apache 2.4 with `mod_rewrite` · Composer

```bash
cd /var/www/html
git clone https://github.com/Langat18/ksg_reporting.git && cd ksg_reporting
composer install --no-dev

sudo -u postgres psql -c "CREATE DATABASE ksg_reports;"
sudo -u postgres psql -c "CREATE USER ksg_user WITH PASSWORD 'your_password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ksg_reports TO ksg_user;"
psql -U ksg_user -d ksg_reports -f database/schema.sql

cp .env.example .env && nano .env   # set DB_HOST=127.0.0.1
chmod 600 .env

# Apache vhost
cat > /etc/apache2/sites-available/ksg.conf << 'EOF'
<VirtualHost *:80>
    ServerName reports.yourdomain.com
    DocumentRoot /var/www/html/ksg_reporting/public
    <Directory /var/www/html/ksg_reporting/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
EOF
a2ensite ksg.conf && a2enmod rewrite && systemctl reload apache2
chown -R www-data:www-data /var/www/html/ksg_reporting
chmod 600 /var/www/html/ksg_reporting/.env

# Cron (crontab -e as www-data or root)
# * * * * * php /var/www/html/ksg_reporting/cron/process_email_queue.php
# 0 4 * * * php /var/www/html/ksg_reporting/cron/overdue_tasks.php
```

---

## Support

| Field     | Detail                    |
|-----------|---------------------------|
| Developer | Langat Clement Kipkirui   |
| Email     | langatclement18@gmail.com |
| Version   | 1.0.0                     |
| Updated   | March 2026                |

---

*© 2026 Kenya School of Government. All rights reserved.*
