# Comet Commuter

A web-based DART transit alert system for UT Dallas students and faculty. Select your destination station, set a proximity radius, and receive a real-time notification when you're approaching your stop.

**Team:** Data Dogs — SE 4347.005 Database Systems, UT Dallas

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8.x)
- A modern web browser (Chrome, Firefox, or Safari)

## Installation

1. **Install and start XAMPP.** Open the XAMPP manager and start both Apache and MySQL.

2. **Copy the project into the XAMPP web root.**

   ```bash
   cp -R CometCommuter /Applications/XAMPP/htdocs/comet-commuter   # macOS
   # or
   xcopy CometCommuter C:\xampp\htdocs\comet-commuter /E /I          # Windows
   ```

3. **Create the database.** Open phpMyAdmin at `http://localhost/phpmyadmin`, click "New", and create a database named `comet_commuter` with collation `utf8mb4_general_ci`.

4. **Build the schema.** In phpMyAdmin, select the `comet_commuter` database, go to the SQL tab, and paste the contents of `create.sql`. Click "Go".

5. **Load the data.** Still in the SQL tab, paste the contents of `load.sql` and click "Go". This inserts 5 DART lines, 28 stations, 33 line-station links, and 10 demo alerts.

6. **Verify.** Browse to `http://localhost/comet-commuter/index.php`. You should see the dashboard with the hero map and animated counters.

## File Structure

```
comet-commuter/
├── index.php                 # Dashboard — alert form, GPS tracking, Leaflet map
├── stations.php              # Browse stations with line filter and search
├── lines.php                 # Browse DART lines with station lists
├── admin_login.php           # Admin login (password: cometadmin2025)
├── admin_panel.php           # CRUD interface for all four tables
├── admin_logout.php          # Destroys admin session
├── db.php                    # Database connection config
├── get_stations.php          # AJAX endpoint — stations filtered by line (JSON)
├── get_station_coords.php    # AJAX endpoint — station lat/lng (JSON)
├── process_alert.php         # POST handler — inserts new alert
├── style.css                 # Global stylesheet
├── create.sql                # CREATE TABLE statements
├── load.sql                  # INSERT statements for sample data
└── README.md
```

## Usage

### Creating an Alert

1. Open the dashboard at `http://localhost/comet-commuter/index.php`.
2. Select a transit line from the dropdown.
3. Select a destination station (filtered by the chosen line).
4. Set your alert radius in meters (default: 200m).
5. Click "Start Tracking". The app inserts an alert into the database and begins GPS tracking.
6. When your position enters the alert radius, a browser notification fires.

### Browsing Stations and Lines

- **Stations** (`stations.php`): Filter by line, search by name, and click "Set Alert" to jump to the dashboard with that station pre-selected.
- **Lines** (`lines.php`): View each DART line as a card with its station list. UT Dallas stops are highlighted.

### Admin Panel

1. Navigate to `http://localhost/comet-commuter/admin_login.php`.
2. Enter the password: `cometadmin2026`.
3. Use the panel to insert or delete stations, lines, and line-station associations. Alerts can be viewed and deleted. All delete operations cascade per the foreign key constraints.

## Database Configuration

Connection settings are in `db.php`. The defaults match a standard XAMPP installation:

```
Host:     localhost
User:     root
Password: (empty)
Database: comet_commuter
```

## Notes

- GPS tracking requires the browser to grant location permission. On localhost, most browsers allow this over HTTP. If GPS does not activate, try accessing via `http://127.0.0.1/comet-commuter/` or use Firefox.
- Admin credentials are validated server-side in `admin_login.php` and are not stored in the database.
- All database queries use prepared statements to prevent SQL injection.
