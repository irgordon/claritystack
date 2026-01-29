# ClarityStack

![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4.svg)
![React](https://img.shields.io/badge/React-18-61DAFB.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-336791.svg)

**ClarityStack** is a high-performance, hybrid CMS and Client Proofing Platform designed specifically for photography studios and creative agencies. It combines a secure, headless PHP backend with a reactive React-based Admin Dashboard.

🌐 **Website:** [iangordon.app/claritystack](https://iangordon.app/claritystack)  
👨‍💻 **Author:** Ian Gordon

---

## 🏗️ Architectural Overview

ClarityStack utilizes a **Hybrid Monolith** architecture. It serves public marketing pages via Server-Side Rendering (SSR) for optimal SEO, while the Admin Dashboard and Client Portal run as a React Single Page Application (SPA).

```mermaid
graph TD
    User((User / Client))
    
    subgraph Server_Environment [Web Server (Nginx/Apache)]
        direction TB
        Router{Routing Layer}
        
        subgraph Public_Zone [Public Web Root]
            Assets[Static Assets / CSS / JS]
            SEO[index.php / SEO Engine]
        end
        
        subgraph App_Zone [React Application]
            SPA[React Admin Dashboard]
            Portal[Client Portal]
        end
    end
    
    subgraph Secure_Backend [Private Backend (Outside Web Root)]
        API[PHP REST API]
        ThemeEngine[Theme Engine]
        Auth[Auth & Security]
        FileProxy[File Proxy Controller]
    end

    subgraph Data_Persistence [Data Layer]
        DB[(PostgreSQL 15)]
        Storage[Secure Storage / Uploads]
    end

    %% Flows
    User -->|HTTPS Request| Router
    
    %% Flow 1: Marketing / Public Site (SSR)
    Router -->|GET /about| SEO
    SEO --> ThemeEngine
    ThemeEngine -->|Fetch Blocks| DB
    ThemeEngine -->|Return HTML| User

    %% Flow 2: App Interaction (SPA)
    Router -->|GET /admin| SPA
    SPA -->|JSON API Calls| API
    API --> Auth
    Auth --> DB
    
    %% Flow 3: Secure File Access
    User -->|GET /api/files/view| FileProxy
    FileProxy -->|Validate Session| Auth
    FileProxy -->|Stream Bytes| Storage
