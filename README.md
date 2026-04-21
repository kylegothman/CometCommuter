# CometCommuter

A PHP + MySQL commuter dashboard with station, line, and alert data.

## Prerequisites

- PHP 8.x (`php -v`)
- MySQL 8.x or compatible (`mysql --version`)

## 1) Clone and enter the project

```bash
git clone https://github.com/kylegothman/CometCommuter.git
cd CometCommuter
```

## 2) Create schema

From the project root:

```bash
mysql -u root -p < create.sql
```

If your MySQL user has no password, you can run:

```bash
mysql -u root < create.sql
```

## 3) Load seed data

The app includes `.dat` files (`lines.dat`, `stations.dat`, `line_stations.dat`, `alerts.dat`) and a loader script (`load.sql`).

Use the following:

```bash
mysql --local-infile=1 -u root -p comet_commuter < load.sql
```

If `LOCAL INFILE` is blocked in your MySQL setup, enable it in your client/server config and retry.

## 5) Run the app

Start PHP's built-in server from the project root:

```bash
php -S localhost:8000
```

Open in browser:

- Main app: `http://localhost:8000`
- Admin login: `http://localhost:8000/admin_login.php`

## Admin access

Current admin password is defined in `admin_login.php`:

- Password: `cometadmin2026`

