# Website Monitoring System with Telegram Alert & Public Status Page

A lightweight, robust, and modern **Website Monitoring System** built with **Core PHP 8+**, **MySQL**, **PDO**, **cURL**, **HTML5/CSS3**, and **Vanilla JavaScript**. Designed for complete compatibility with both **XAMPP (Windows)** and **cPanel (Linux)** environments with zero third-party framework overhead.

---

## 🌟 Key Features

* **Admin Authentication**: Session-based login with bcrypt password hashing (`password_hash`), show/hide password toggle, and CSRF token protection.
* **Modern Admin Dashboard**: Real-time KPI statistics (Total, 🟢 UP, 🔴 DOWN, 🟡 SLOW), recent check logs, and incident timelines.
* **Full Website Management**: Add, edit, delete, and enable/disable endpoints with custom monitoring intervals (1 to 60 minutes) and customizable latency thresholds (ms).
* **Automated cURL Monitoring Engine**: Real-time cURL pinging measuring response times, capturing HTTP status codes, and detecting outages.
* **Instant Telegram Alerts**: Delivers formatted status notifications via Telegram Bot API on state changes (DOWN 🚨, RECOVERY ✅, SLOW WARNING ⚠️) with duplicate alert prevention.
* **Public Status Page (UptimeRobot-style)**:
  * Publicly accessible (`/status.php`) with **no login required**.
  * Zero exposure of sensitive admin or database credentials.
  * System-wide hero banner (All Systems Operational / Partial Outage / Degraded).
  * **90-Day Visual History Bar** for every service with interactive hover tooltips displaying date, total checks, UP/DOWN/SLOW counts, and true calculated uptime percentages.
  * Dedicated Website Detail View (`/status.php?site=ID`).
  * Incident history timeline tracking outage start times, recovery times, and exact downtime duration.
* **90-Day Log Retention & Auto Cleanup**: Includes an automated cron script to purge monitoring logs older than 90 days.
* **Environment Variables (.env)**: Secrets and database credentials are stored in `.env` and excluded from git via `.gitignore`.
* **Malaysia Timezone**: Configured with `Asia/Kuala_Lumpur` across all logs, alerts, and views.

---

## 📁 Project Structure

```text
monitoring_alert_system/
│
├── .env.example              # Environment template for GitHub
├── .gitignore                # Prevents committing .env and secrets
├── index.php                 # Public entry point (routes to status.php)
├── login.php                 # Admin login with show/hide password & CSRF
├── logout.php                # Safe session termination
├── status.php                # Public Status Page with 90-day interactive history
│
├── admin/
│   ├── dashboard.php         # Admin metrics & recent check activity
│   ├── websites.php          # Manage monitored websites (Add/Edit/Delete/Toggle)
│   ├── website-add.php       # Add website form
│   ├── website-edit.php      # Edit website settings
│   ├── logs.php              # Filterable logs (site, status, date) & pagination
│   ├── incidents.php         # Historical incidents and downtime duration logs
│   └── settings.php          # Telegram Bot settings, profile management, and cron guide
│
├── cron/
│   ├── monitor.php           # Core cURL monitoring engine (Run every minute)
│   └── cleanup.php           # Purges logs older than 90 days (Run daily)
│
├── config/
│   └── config.php            # Loads .env, constants, and base URL setup
│
├── includes/
│   ├── env.php               # Native Core PHP .env parser
│   ├── auth.php              # Authentication and session verification
│   ├── database.php          # Reusable PDO database connection
│   ├── functions.php         # Sanitization, CSRF, date formatting, and 90-day math
│   ├── telegram.php          # Telegram Bot API notification engine
│   ├── header.php            # Shared header component
│   └── footer.php            # Shared footer component
│
├── assets/
│   ├── css/
│   │   └── style.css         # Modern, responsive CSS styling & 90-day bars
│   └── js/
│       └── script.js         # Vanilla JS helpers (password toggle, tooltips, timers)
│
├── database/
│   └── database.sql          # MySQL database schema and seed data
└── README.md
```

---

## 🚀 Setup on XAMPP (Localhost Windows)

1. **Place Project in `htdocs`**:
   Copy or extract this folder to:
   ```text
   C:\xampp\htdocs\monitoring_alert_system
   ```
2. **Start Services**:
   Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. **Create Database & Import Schema**:
   * Open phpMyAdmin: `http://localhost/phpmyadmin`
   * Click **Import** and select `database/database.sql` (or create database `db_website_monitor` and run the SQL script).
4. **Configure Environment Variables (`.env`)**:
   Copy `.env.example` to `.env` (if not already created) and configure your database:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=db_website_monitor
   DB_USER=root
   DB_PASS=
   ```
5. **Access the Application**:
   * **Public Status Page**: `http://localhost/monitoring_alert_system/status.php`
   * **Admin Login**: `http://localhost/monitoring_alert_system/login.php`
     * **Default Username**: `admin`
     * **Default Password**: `admin123`
6. **Run Initial Test Check**:
   * From the Admin Dashboard, click **🔄 Check All Websites Now** to immediately ping all websites.
   * Or run the cron script directly in PowerShell / Command Prompt:
     ```powershell
     php C:\xampp\htdocs\monitoring_alert_system\cron\monitor.php
     ```

---

## 🌐 Setup on cPanel (Production Linux)

1. **Upload Files**:
   Upload the project files to your cPanel `public_html/` or a subfolder.
2. **Create MySQL Database**:
   * In cPanel, go to **MySQL® Databases**.
   * Create a database and user with **ALL PRIVILEGES**.
3. **Import SQL Schema**:
   * Open **phpMyAdmin** in cPanel.
   * Select your database, click **Import**, and upload `database/database.sql`.
4. **Create `.env` File**:
   Copy `.env.example` to `.env` and fill in your database credentials:
   ```ini
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=cpaneluser_db_monitor
   DB_USER=cpaneluser_db_user
   DB_PASS="your_secure_password"
   ```
5. **Set Up Cron Jobs in cPanel**:
   * **Website Monitoring (Every 1 minute)**:
     ```bash
     * * * * * /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/cron/monitor.php >/dev/null 2>&1
     ```
   * **90-Day Cleanup (Once daily at midnight)**:
     ```bash
     0 0 * * * /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/cron/cleanup.php >/dev/null 2>&1
     ```

---

## 🤖 Telegram Bot Configuration

1. Open Telegram and search for `@BotFather`.
2. Send `/newbot` and follow instructions to get your **Bot Token**.
3. Search for `@userinfobot` or add your bot to your alert group/channel to get your **Chat ID**.
4. In Admin panel &rarr; **Settings**, enter your **Bot Token** and **Chat ID**, toggle **Enable Instant Telegram Alerts**, and click **Save**.
5. Click **🔔 Test Telegram Alert** to verify instant delivery.

---

## 🛡️ Security & Git Best Practices

* Sensitive credentials are kept in `.env` which is ignored by `.gitignore`.
* Never commit `.env` to GitHub. Commit `.env.example` instead.
* All queries use PDO prepared statements.
* Passwords hashed using bcrypt `password_hash()`.
* CSRF protection enabled on all admin forms.
