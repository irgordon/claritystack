# ClarityStack

**ClarityStack** is a secure, high-performance SaaS platform designed for photography studios and creative agencies. It features a custom **Headless CMS**, a secure **Client Proofing Portal**, and automated **Stripe Booking**.

## 🚀 Core Capabilities

### 🎨 Theming & CMS
* **File-Based Theme Engine:** PHP-based layouts and blocks allow for complex server-side logic while remaining safe for Admin editing.
* **Recursive Page Builder:** Admins can build nested layouts (Columns inside Containers) using a drag-and-drop React UI.
* **Safe Mode:** The engine automatically catches block rendering errors, logs them, and prevents "White Screen of Death" failures.

### 🛡️ Security Architecture
* **Trust Boundaries:** Strict separation between Public (Marketing), Client (Portal), and Admin (Management) zones.
* **Secure Storage:** Original high-res files are stored outside the web root. Access is proxied via IDOR-protected endpoints.
* **Operational Visibility:** Health Dashboard tracks critical errors, disk space, and recent failures with automated email alerts.

### ⚡ Performance & SEO
* **Server-Side Injection:** Dynamic meta tags and OpenGraph data injected via PHP before React loads for perfect social sharing.
* **Optimized Galleries:** Server-side thumbnail generation (GD Library) and Infinite Scroll prevent browser crashes on large galleries.
* **Google PageSpeed:** Built-in settings for GZIP, Lazy Loading, and Deferring Analytics.

## 🛠️ Technology Stack
* **Backend:** PHP 8.2+ (No framework, pure PSR-4 architecture)
* **Frontend:** React 18, TailwindCSS
* **Database:** PostgreSQL 15+ (JSONB used for CMS/Config)
* **Infrastructure:** Docker compatible, Local/S3 Storage Adapters

## 📦 License
MIT License
