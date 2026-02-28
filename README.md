KENYA SCHOOL OF GOVERNMENT
Weekly Status Reporting System
Deployment & Technical Documentation
1. System Overview
The KSG Weekly Status Reporting System is a web-based PHP application that enables staff across all Kenya School of Government campuses to submit, manage, and view weekly departmental activity reports.

Application
KSG Weekly Status Report System
Version
1.0.0
Language
PHP 8.2
Database
PostgreSQL 17
Web Server
Apache 2.4
Email
SMTP via Gmail / PHPMailer

2. Server Requirements
2.1 Minimum Requirements
PHP
8.2 or higher
PostgreSQL
14 or higher
Apache
2.4 with mod_rewrite enabled
RAM
512 MB minimum, 1 GB recommended
Storage
5 GB minimum
OS
Ubuntu 22.04 LTS (recommended)

2.2 Required PHP Extensions
	•	pdo_pgsql — PostgreSQL database driver
	•	pdo — PHP Data Objects
	•	openssl — SSL/TLS support for email
	•	mbstring — Multi-byte string handling
	•	tokenizer — PHP tokenizer
	•	xml — XML processing
	•	ctype — Character type functions

3. Installation Guide
3.1 Step 1 — Install Dependencies
Run the following commands on your Ubuntu server:

sudo apt update && sudo apt upgrade -y
sudo apt install apache2 php8.2 php8.2-pgsql php8.2-mbstring php8.2-xml -y
sudo apt install postgresql postgresql-contrib -y
sudo a2enmod rewrite
sudo systemctl restart apache2

3.2 Step 2 — Clone the Repository
cd /var/www/html
git clone https://github.com/Langat18/ksg_reporting.git
cd ksg_reporting

3.3 Step 3 — Install PHP Dependencies
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev

3.4 Step 4 — Set Up the Database
sudo -u postgres psql
CREATE DATABASE ksg_reports;
CREATE USER ksg_user WITH PASSWORD 'your_strong_password';
GRANT ALL PRIVILEGES ON DATABASE ksg_reports TO ksg_user;
\q

Run the database migrations:
psql -U ksg_user -d ksg_reports -f database/schema.sql
psql -U ksg_user -d ksg_reports -f database/migration_report_code.sql
psql -U ksg_user -d ksg_reports -f database/migration_user_profile.sql

3.5 Step 5 — Configure Environment
Copy the example environment file and edit it with your production values:
cp .env.example .env
nano .env

Required .env values:

Variable
Example Value
Description
DB_HOST
127.0.0.1
Database server IP
DB_PORT
5432
PostgreSQL port
DB_NAME
ksg_reports
Database name
DB_USER
ksg_user
Database username
DB_PASS
your_password
Database password
APP_URL
https://reports.ksg.ac.ke
Full application URL
MAIL_HOST
smtp.gmail.com
SMTP mail server
MAIL_PORT
587
SMTP port
MAIL_USERNAME
noreply@ksg.ac.ke
Sender email address
MAIL_PASSWORD
app_password
SMTP app password

3.6 Step 6 — Apache Virtual Host
Create a virtual host configuration:
sudo nano /etc/apache2/sites-available/ksg-reports.conf

Add the following configuration:
<VirtualHost *:80>
    ServerName reports.ksg.ac.ke
    DocumentRoot /var/www/html/ksg_reporting/public
    <Directory /var/www/html/ksg_reporting/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/ksg-error.log
    CustomLog ${APACHE_LOG_DIR}/ksg-access.log combined
</VirtualHost>

sudo a2ensite ksg-reports.conf
sudo systemctl reload apache2

3.7 Step 7 — File Permissions
sudo chown -R www-data:www-data /var/www/html/ksg_reporting
sudo chmod -R 755 /var/www/html/ksg_reporting
sudo chmod 600 /var/www/html/ksg_reporting/.env

3.8 Step 8 — Create Admin User
cd /var/www/html/ksg_reporting
php scripts/create_user.php
Follow the prompts to create the first administrator account.

4. User Roles & Permissions

Role
Permissions
Staff
Submit reports. View only their own submitted reports.
HoD
Submit reports. View all reports for their campus and department.
Director
View all reports for their campus across all departments. Cannot submit reports.
Admin
Full access. View all reports from all campuses. Create and manage users.

5. Report Reference Codes
Each report is automatically assigned a unique reference code based on the campus:

Nairobi (Main Campus)
KSG/01/NBO/XX
Mombasa Campus
KSG/01/MSA/XX
Matuga Campus
KSG/01/MTG/XX
Embu Campus
KSG/01/EBU/XX
Baringo Campus
KSG/01/BRG/XX

XX is a sequential two-digit number auto-incremented per campus.

6. Directory Structure
ksg_reporting/
├── config/          # Database config and constants
├── database/        # SQL migration files
├── public/          # Web root (Apache DocumentRoot)
│   ├── assets/      # CSS, JS, images
│   ├── .htaccess    # URL rewriting rules
│   └── *.php        # Public page controllers
├── scripts/         # CLI utilities (create_user.php)
├── src/             # Core classes (Auth, Report, Database, Mailer)
├── templates/       # HTML templates (header, footer, views)
├── vendor/          # Composer dependencies
├── .env             # Environment configuration (never commit)
└── .env.example     # Environment template (safe to commit)

7. Deploying Updates
To deploy code updates from the Git repository:

	•	Pull latest changes from Git:
cd /var/www/html/ksg_reporting && git pull origin main

	•	Install any new dependencies:
php composer.phar install --no-dev

	•	Run any new migrations:
psql -U ksg_user -d ksg_reports -f database/new_migration.sql

	•	Fix permissions if needed:
sudo chown -R www-data:www-data /var/www/html/ksg_reporting

8. Security Checklist
	•	Never commit the .env file to Git
	•	Set .env file permissions to 600
	•	Use a strong database password (minimum 16 characters)
	•	Enable HTTPS using Let's Encrypt SSL certificate
	•	Keep PHP and PostgreSQL updated to latest patch versions
	•	Configure PostgreSQL pg_hba.conf to use md5 authentication
	•	Restrict database user to only required privileges
	•	Set up regular automated database backups

9. Enabling HTTPS (SSL)
Install Certbot for free SSL via Let's Encrypt:
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d reports.ksg.ac.ke
Certbot will automatically configure Apache and renew certificates. Test renewal with:
sudo certbot renew --dry-run

10. Database Backup
Create a daily backup cron job:
crontab -e

Add the following line to run a backup every day at 2:00 AM:
0 2 * * * pg_dump -U ksg_user ksg_reports > /backups/ksg_$(date +\%Y\%m\%d).sql

11. Support & Contact
System Developer
Langat Clement Kipkirui
Email
langatclement18@gmail.com
Application
KSG Weekly Status Report v1.0.0
Documentation Date
28 February 2026

© 2026 Kenya School of Government. All rights reserved.
