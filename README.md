# ⚡ Website Monitoring System with Telegram Alert & Public Status Page

A lightweight, robust, and framework-free **Website Monitoring System** built with **Core PHP 8+**, **MySQL**, **PDO**, **cURL**, **HTML5/CSS3**, and **Vanilla JavaScript**. Designed for complete compatibility with both **XAMPP (Windows)** and **cPanel (Linux)** environments.

---

## 📖 Table of Contents

1. [Step 1: Installation & File Placement](#-step-1-installation--file-placement)
2. [Step 2: Database Setup & SQL Import](#-step-2-database-setup--sql-import)
3. [Step 3: Configure Environment Variables (.env)](#-step-3-configure-environment-variables-env)
4. [Step 4: Admin Login & Password Security](#-step-4-admin-login--password-security)
5. [Step 5: Adding & Managing Websites](#-step-5-adding--managing-websites)
6. [Step 6: Setting Up Instant Telegram Alerts](#-step-6-setting-up-instant-telegram-alerts)
7. [Step 7: Automating 24/7 Monitoring (Cron Jobs)](#-step-7-automating-247-monitoring-cron-jobs)
8. [Step 8: Viewing the Public Status Page](#-step-8-viewing-the-public-status-page)
9. [Step 9: Reviewing Logs & Incidents History](#-step-9-reviewing-logs--incidents-history)
10. [Folder Structure](#-folder-structure)

---

## 🚀 Step 1: Installation & File Placement

### Option A: Localhost (XAMPP on Windows)
1. Copy or extract this project folder to your XAMPP `htdocs` directory:
   ```text
   C:\xampp\htdocs\monitoring_alert_system
   ```
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

### Option B: Production (cPanel / Linux Hosting)
1. Log into your cPanel and open **File Manager**.
2. Upload and extract the project files into your `public_html/` or a subfolder (e.g. `public_html/status/`).

---

## 🗄️ Step 2: Database Setup & SQL Import

1. Open **phpMyAdmin**:
   * **XAMPP**: `http://localhost/phpmyadmin`
   * **cPanel**: Click **phpMyAdmin** in your cPanel dashboard.
2. Create a new database:
   * Database Name: `db_website_monitor` (or your custom cPanel database name).
   * Collation: `utf8mb4_unicode_ci`.
3. Click the **Import** tab.
4. Choose the schema file: [`database/database.sql`](database/database.sql) and click **Import** (or **Go**).

---

## ⚙️ Step 3: Configure Environment Variables (.env)

1. In the project root folder, copy `.env.example` and rename it to `.env`:
   * **Windows / Terminal**:
     ```bash
     cp .env.example .env
     ```
2. Open `.env` in any text editor and configure your database credentials:

```ini
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=db_website_monitor
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Application Details
APP_NAME="Website Monitoring System"
APP_TIMEZONE=Asia/Kuala_Lumpur

# Security & Cron Key
CRON_SECRET_KEY=monitor_cron_secret_2026

# Optional: Base URL override (Leave blank for automatic detection)
BASE_URL=
```

> [!NOTE]
> The `.env` file is excluded from Git by `.gitignore` so your private passwords and tokens are never exposed to GitHub.

---

## 🔐 Step 4: Admin Login & Password Security

1. Open your browser and navigate to the admin login page:
   * **Localhost**: `http://localhost/monitoring_alert_system/login.php`
   * **cPanel**: `https://yourdomain.com/login.php`
2. Sign in with the default administrator credentials:
   * **Username**: `admin`
   * **Password**: `admin123`
3. *(Recommended)* Go to **Settings** &rarr; **Admin Security & Profile** to update your username and password.

---

## 🌐 Step 5: Adding & Managing Websites

1. In the Admin Dashboard, click **Websites** in the navigation bar or click **➕ Add Website**.
2. Fill in the website monitoring parameters:
   * **Website Name**: e.g., `My Online Store`
   * **Website URL**: Full URL including `https://` or `http://` (e.g., `https://example.com`)
   * **Monitoring Interval**: Ping frequency (1, 2, 5, 10, 15, 30, or 60 minutes)
   * **Slow Response Threshold**: Latency in milliseconds that triggers a SLOW alert (default: `3000` ms)
   * **Enable Monitoring**: Toggle active/paused state
3. Click **Save and Start Monitoring**.
4. You can perform instant actions from the **Websites** list:
   * 🔄 **Check**: Trigger an immediate live ping test.
   * ✏️ **Edit**: Change URLs, intervals, or thresholds.
   * **Active / Paused**: Toggle monitoring state on or off.
   * 🗑️ **Delete**: Remove a website and its history.

---

## 🤖 Step 6: Setting Up Instant Telegram Alerts

Receive instant notifications for:
* 🚨 **Outages (UP &rarr; DOWN)**
* ✅ **Recoveries (DOWN &rarr; UP)**
* ⚠️ **High Latency Warnings (UP &rarr; SLOW)**

### 1. Get your Telegram Bot Token
1. Open Telegram and search for [@BotFather](https://t.me/BotFather).
2. Send `/newbot` and follow the prompts to choose a name and username.
3. Copy the **HTTP API Token** provided (e.g. `1234567890:ABCdefGhIJKlmNoPQRstuVWXyz`).

### 2. Get your Telegram Chat ID
1. Search for [@userinfobot](https://t.me/userinfobot) on Telegram and start it to get your personal **Id** (e.g. `987654321`).
2. *For Group / Channel Alerts*: Add your bot to the group/channel as an admin and use the group's Chat ID (e.g. `-1001234567890`).

### 3. Save & Test in Admin Settings
1. Go to Admin &rarr; **Settings**.
2. Enter your **Telegram Bot Token** and **Telegram Chat ID**.
3. Check **Enable Instant Telegram Alerts**.
4. Click **💾 Save Telegram Settings**.
5. Click **🔔 Test Telegram Alert** to verify instant message delivery.

---

## ⏱️ Step 7: Automating 24/7 Monitoring (Cron Jobs)

To automatically ping websites in the background, set up the cron jobs below.

### Method 1: cPanel Cron Jobs (Linux Hosting - Recommended)
In cPanel, search for **Cron Jobs** and create these two tasks:

1. **Website Monitoring (Runs every minute)**:
   * Schedule: `* * * * *` (Every Minute)
   * Command:
     ```bash
     /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/cron/monitor.php >/dev/null 2>&1
     ```
2. **90-Day History Cleanup (Runs daily at midnight)**:
   * Schedule: `0 0 * * *` (Once Per Day)
   * Command:
     ```bash
     /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/cron/cleanup.php >/dev/null 2>&1
     ```

---

### Method 2: Windows Task Scheduler (XAMPP Localhost)
1. Press <kbd>Win</kbd> + <kbd>R</kbd>, type `taskschd.msc`, and press **Enter**.
2. Click **Create Task...**:
   * **General**: Name it `Website Monitor Cron`.
   * **Triggers**: New &rarr; Daily &rarr; Check **Repeat task every 1 minute** &rarr; Duration: **Indefinitely**.
   * **Actions**: New &rarr; Action: *Start a program*:
     * Program/script: `C:\xampp\php\php.exe`
     * Add arguments: `C:\xampp\htdocs\monitoring_alert_system\cron\monitor.php`
3. Click **OK** to save.

---

### Method 3: PowerShell Continuous Loop (Localhost Testing)
If you are testing locally without Task Scheduler, open PowerShell in the project directory and run:
```powershell
while ($true) { php cron\monitor.php; Start-Sleep -Seconds 60 }
```

---

### Method 4: Web Cron (Free Online Cron Services)
Use services like [cron-job.org](https://cron-job.org) or [EasyCron](https://www.easycron.com) to ping your web cron URLs:
* `https://yourdomain.com/cron/monitor.php?key=monitor_cron_secret_2026` (Every 1 minute)
* `https://yourdomain.com/cron/cleanup.php?key=monitor_cron_secret_2026` (Every 24 hours)

---

## 📊 Step 8: Viewing the Public Status Page

* Open: `http://localhost/monitoring_alert_system/status.php` (or `https://yourdomain.com/status.php`).
* **Zero Login Required**: Safe for public visitors and team members.
* **Hero Status Banner**: Displays overall system health (🟢 Operational / 🔴 Outage / 🟡 Degraded).
* **90-Day Visual History**: Hover or tap on any day block to inspect date, total checks, UP/DOWN/SLOW breakdown, average latency, and true uptime percentage.
* **Website Detail View**: Click on any service name (e.g. `/status.php?site=1`) to view individual uptime metrics and dedicated incident history.

---

## 📋 Step 9: Reviewing Logs & Incidents History

* **Monitoring Logs (`admin/logs.php`)**:
  * Filter raw cURL logs by **Website**, **Status (UP/DOWN/SLOW)**, and **Date Range (Today, 7 days, 30 days, 90 days)** with built-in pagination.
* **Incidents Tracker (`admin/incidents.php`)**:
  * Automatically records whenever an endpoint transitions into `DOWN` status.
  * Calculates total downtime duration upon recovery (e.g. `5m`, `1h 20m`).
  * Option to manually mark an active incident as resolved.

---

## 📁 Folder Structure

```text
monitoring_alert_system/
│
├── .env                      # Local private credentials (ignored by git)
├── .env.example              # Configuration template for GitHub
├── .gitignore                # Git exclusions
├── index.php                 # Public entry point (redirects to status.php)
├── login.php                 # Admin login with show/hide password & CSRF
├── logout.php                # Session termination
├── status.php                # Public Status Page with 90-day visual history
│
├── admin/
│   ├── dashboard.php         # Real-time metrics & recent check activity
│   ├── websites.php          # Manage monitored websites (CRUD & toggles)
│   ├── website-add.php       # Add new website form
│   ├── website-edit.php      # Edit website settings
│   ├── logs.php              # Filterable monitoring logs with pagination
│   ├── incidents.php         # Historical incidents and downtime logs
│   └── settings.php          # Telegram Bot settings & admin credentials
│
├── cron/
│   ├── monitor.php           # Core cURL monitoring engine (Runs every minute)
│   └── cleanup.php           # Purges logs older than 90 days (Runs daily)
│
├── config/
│   └── config.php            # Loads .env, constants, and base URL setup
│
├── includes/
│   ├── env.php               # Native Core PHP .env parser
│   ├── auth.php              # Session authentication & route protection
│   ├── database.php          # Reusable PDO database connection
│   ├── functions.php         # Sanitization, CSRF, date math, & 90-day aggregations
│   ├── telegram.php          # Telegram Bot API notification engine
│   ├── header.php            # Shared header component
│   └── footer.php            # Shared footer component
│
├── assets/
│   ├── css/
│   │   └── style.css         # Responsive styling & 90-day history bars
│   └── js/
│       └── script.js         # Vanilla JS helpers (tooltips, toggles, timers)
│
├── database/
│   └── database.sql          # MySQL database schema and seed data
└── README.md                 # Complete Step-by-Step User Guide
```
