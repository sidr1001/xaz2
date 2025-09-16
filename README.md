Tours CMS (Slim 4 + Twig + PDO)

Requirements
- PHP 8+
- MySQL 5.7+/8+
- Composer

Install
1) Copy env and install deps
```
cp config/.env.example config/.env
composer install
```

2) Configure DB in `config/.env`.

3) Create database and import schema
```
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS tours_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p tours_cms < sql/schema.sql
```

Run (Dev server)
```
composer start
```
Open `http://localhost:8080`.

Admin Panel
- URL: `/admin`
- Default credentials: `admin` / `admin` (set in `config/.env`)

Features
- Public tours list with filters
- Tour details page
- Admin auth via session middleware
- Tours CRUD with image upload
- Agents CRUD with permissions text field
- Twig templates, Bootstrap 5 UI, jQuery helpers
- Pretty URLs via `.htaccess`

Security Notes
- Demo-only: passwords are stored in plain text; use password_hash in production.
- All user inputs are validated minimally and escaped in Twig.

Project Structure
```
public/          # front controller, .htaccess, uploads/
src/             # controllers, middleware, services, routes.php
templates/       # Twig templates (frontend + admin)
assets/          # CSS/JS
config/          # .env
sql/             # schema and seeds
```

# xaz1