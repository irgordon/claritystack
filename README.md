# ClarityStack

![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4.svg)
![React](https://img.shields.io/badge/React-18-61DAFB.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-336791.svg)

**ClarityStack** is a high-performance, hybrid CMS and Client Proofing Platform designed specifically for photography studios and creative agencies. It combines a secure, headless PHP backend with a reactive React-based Admin Dashboard.

🌐 **Website:** [iangordon.app/claritystack](https://iangordon.app/claritystack)  
👨‍💻 **Author:** Ian Gordon

---

## 🚀 Key Features

### 🎨 Hybrid Theme Engine
* **Performance:** Public marketing pages (Home, Portfolio, Pricing) are server-side rendered (SSR) via PHP for 100/100 Lighthouse SEO scores.
* **Reactivity:** The Admin Dashboard and Client Portal operate as a Single Page Application (SPA) using React for a fluid user experience.
* **Recursive Block Builder:** A drag-and-drop page builder that allows nested layouts (e.g., Columns inside Containers) with a robust "Safety Net" that prevents white-screen crashes.

### 📸 Secure Client Proofing
* **IDOR Protection:** High-resolution original files are stored **outside** the web root. Access is proxied via a secure PHP controller that verifies session ownership before streaming bytes.
* **Magic Links:** Passwordless authentication for clients via secure, time-bound email links.
* **Secure Downloads:** Large ZIP downloads are generated on-the-fly and streamed via signed, one-time-use URLs to prevent memory exhaustion and unauthorized sharing.

### 💰 Studio Management
* **Stripe Integration:** Automated booking flow with webhook verification (Signature & Idempotency checks).
* **EXIF Extraction:** Automatically extracts camera, lens, and exposure data from uploaded JPEGs for display.
* **Dynamic Watermarking:** (Optional) On-the-fly watermark injection for unpaid proofing galleries.

---

## 🛠️ Tech Stack

* **Backend:** PHP 8.2+ (PSR-4 Autoloading, No Heavy Frameworks)
* **Frontend:** React 18, TailwindCSS, Vite
* **Database:** PostgreSQL 15 (JSONB used for flexible Content Blocks)
* **Infrastructure:** Docker-ready, Local Filesystem or S3 Storage Adapters

---

## 📂 Directory Structure

```text
ClarityStack/
├── api/                  # PHP Core & Controllers
│   ├── core/             # ThemeEngine, Security, Database
│   └── controllers/      # API Endpoints
├── themes/               # CMS Themes
│   └── clarity_default/  # The default 'Ian Gordon Photography' theme
├── src/                  # React Admin Dashboard (Source)
├── public/               # Web Root (Entry Point)
├── storage_secure/       # Private Uploads (Outside Web Root)
└── database/             # SQL Schema & Migrations
