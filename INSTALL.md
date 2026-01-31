# Installation Guide for ClarityStack

This guide covers the complete installation process for ClarityStack, including system requirements, initial setup, frontend building, and configuring third-party storage.

## 1. System Requirements

Ensure your server meets the following requirements before proceeding:

*   **PHP**: Version 8.1 or higher.
*   **Extensions**: `pdo`, `pdo_pgsql`, `json`, `mbstring`, `filter`, `ctype`, `session`, `openssl`.
*   **Database**: PostgreSQL 15+.
*   **Node.js**: Version 18+ (for building the frontend).
*   **Web Server**: Apache or Nginx.

## 2. Step-by-Step Installation

### Step 1: Clone the Repository

Download the source code to your server or local environment.

```bash
git clone https://github.com/irgordon/claritystack.git
cd claritystack
```

### Step 2: Build the Frontend

ClarityStack uses a React frontend that needs to be compiled.

1.  **Install Dependencies**:
    ```bash
    npm install
    ```

2.  **Build the Application**:
    This compiles the React app into the `public_html` directory.
    ```bash
    npm run build
    ```

    *Note: This will generate `index.html` and an `assets/` directory inside `public_html`.*

### Step 3: Database Setup

Create a new PostgreSQL database and a user with full privileges on that database.

```sql
CREATE DATABASE clarity_db;
CREATE USER clarity_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE clarity_db TO clarity_user;
```

### Step 4: Directory Permissions

Ensure the application has write access to the following directories:

1.  **Configuration Directory**: The installer needs to write the `env.php` file.
    ```bash
    chmod -R 755 clarity_app/api/config
    chown -R www-data:www-data clarity_app/api/config
    ```

2.  **Storage Directory**: Create a directory for secure file storage (outside the web root if possible, but the default is in the project root).
    ```bash
    mkdir storage_secure
    chmod -R 755 storage_secure
    chown -R www-data:www-data storage_secure
    ```

### Step 5: Web Server Configuration

Point your web server's document root to the `public_html` directory.

**Apache**:
Ensure `mod_rewrite` is enabled. The included `.htaccess` file handles routing.

**Nginx**:
Configure your server block to route all requests to `index.php`.

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/claritystack/public_html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Block access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

### Step 6: Run the Installer

1.  Open your browser and navigate to `http://your-domain.com/install`.
2.  Fill in the **Database Credentials** (Host, Name, User, Password).
3.  Set up your **Admin Account** (Email, Password).
4.  Enter your **Business Details**.
5.  Click **Install**.

Once complete, you will be redirected to the Admin Login page.

---

## 3. Post-Installation Configuration

### Configuring Storage

By default, ClarityStack stores files on the local disk (`storage_secure`). To use third-party cloud storage (AWS S3, Cloudinary, etc.), follow these steps:

1.  **Log in** to the Admin Dashboard.
2.  Navigate to **Settings** > **Storage**.
3.  Select your desired **Storage Driver**.

#### AWS S3
*   **Region**: e.g., `us-east-1`
*   **Bucket**: Your S3 bucket name.
*   **Access Key ID**: Your AWS Access Key.
*   **Secret Access Key**: Your AWS Secret Key.

#### Cloudinary
*   **Cloud Name**: Found in your Cloudinary Dashboard.
*   **API Key**: Found in your Cloudinary Dashboard.
*   **API Secret**: Found in your Cloudinary Dashboard.

#### ImageKit
*   **URL Endpoint**: e.g., `https://ik.imagekit.io/your_id`
*   **Public Key**: From Developer Options.
*   **Private Key**: From Developer Options.

**Important**:
*   API Keys are encrypted in the database using the `APP_KEY` generated during installation.
*   ClarityStack operates in a "Hybrid" mode where you can switch storage providers. Existing files usually remain on the original provider unless migrated (migration features depend on version).

### Configuring Email (SMTP)

To enable email notifications (Magic Links, etc.):
1.  Navigate to **Settings** > **Email**.
2.  Enter your SMTP credentials (Host, Port, User, Password).

---

## 4. Troubleshooting

*   **White Screen / 404 Errors**: Ensure your web server is correctly routing requests to `index.php`. Check `.htaccess` or Nginx config.
*   **Permission Denied**: Check that `clarity_app/api/config/` is writable by the web server user.
*   **Database Connection Failed**: Verify credentials and ensuring the `pdo_pgsql` extension is enabled in `php.ini`.
*   **Build Failures**: Ensure you are using Node.js 18+ and have run `npm install` successfully.
