# Installation Guide for ClarityStack

This guide covers the complete installation process for ClarityStack on a generic VPS (Virtual Private Server) running Nginx and PostgreSQL.

## 1. System Requirements

Ensure your server meets the following requirements before proceeding:

*   **Operating System**: Ubuntu 22.04 LTS (Recommended) or any modern Linux distribution.
*   **Web Server**: Nginx (Preferred) or Apache.
*   **PHP**: Version 8.1 or higher.
*   **Database**: PostgreSQL 15+.
*   **Node.js**: Version 18+ (Required for building the frontend assets).

### Required PHP Extensions
Ensure the following extensions are installed and enabled in your `php.ini`:
*   `pdo`
*   `pdo_pgsql`
*   `json`
*   `mbstring`
*   `openssl`
*   `ctype`
*   `filter`
*   `session`
*   `zip`
*   `gd` (for image processing)

## 2. Step-by-Step Installation

### Step 1: Clone the Repository

Navigate to your web root (e.g., `/var/www`) and clone the repository.

```bash
cd /var/www
git clone https://github.com/irgordon/claritystack.git claritystack
cd claritystack
```

### Step 2: Directory Permissions

The application requires write access to specific directories for configuration, logging, and storage.

```bash
# Set ownership to your web server user (usually www-data)
chown -R www-data:www-data /var/www/claritystack

# permissions for sensitive directories
chmod -R 755 clarity_app/api/config
chmod -R 755 clarity_app/logs
chmod -R 755 public_html
```

*Note: If you plan to use local storage, create the storage directory:*
```bash
mkdir storage_secure
chown -R www-data:www-data storage_secure
chmod -R 750 storage_secure
```

### Step 3: Database Setup

Create a secure PostgreSQL database and user.

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE clarity_db;
CREATE USER clarity_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE clarity_db TO clarity_user;
-- Grant usage on public schema (sometimes needed)
GRANT ALL ON SCHEMA public TO clarity_user;
\q
```

### Step 4: Build the Frontend

ClarityStack uses a React frontend that must be compiled before deployment.

```bash
# Install Node.js dependencies
npm install

# Build the production assets
npm run build
```

This process compiles the React application into the `public_html` directory.

### Step 5: Configure Nginx

Create a new Nginx server block configuration.

```bash
sudo nano /etc/nginx/sites-available/claritystack
```

Paste the following configuration (adjust `server_name` and paths):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/claritystack/public_html;
    index index.php;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    location / {
        # Route everything to index.php
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Pass PHP scripts to FastCGI server
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # Adjust PHP version as needed
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

Enable the site and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/claritystack /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 6: Run the Installer

1.  Open your web browser and navigate to `http://your-domain.com/install`.
2.  **Step 1: Database Connection**: Enter the credentials you created in Step 3 (Host: `localhost`, Name: `clarity_db`, User: `clarity_user`, Password: `...`).
3.  **Step 2: Business Details**: Enter your Studio Name and branding preferences.
4.  **Step 3: Admin Account**: Create your Super Admin login credentials.
5.  Click **Complete Setup**.

The installer will write the `env.php` configuration file, run database migrations, and seed the initial data. You will be redirected to the Admin Login page.

---

## 3. Post-Installation Configuration

### Email Setup (SMTP)
To enable Magic Links and notifications, log in to the Admin Dashboard and go to **Settings > Email**. Enter your SMTP credentials (e.g., SendGrid, Postmark, AWS SES).

### Storage Setup
By default, files are stored on the local disk in `storage_secure`. To use Cloud Storage (AWS S3, Cloudinary), go to **Settings > Storage** and provide your API keys.

## 4. Troubleshooting

*   **"File not found" or 404 on API calls**: Ensure your Nginx configuration contains `try_files $uri $uri/ /index.php?$query_string;`.
*   **Database Connection Error**: Verify `pdo_pgsql` is enabled and your password is correct. Check `clarity_app/api/config/env.php` manually if needed.
*   **Permission Denied**: Ensure `www-data` has write access to `clarity_app/api/config`.

---
