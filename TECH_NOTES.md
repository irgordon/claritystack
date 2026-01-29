### Technical Architecture Overview

```markdown
# ClarityStack Technical Documentation

## 1. Architectural Overview

ClarityStack operates on a **Hybrid Monolith** architecture. It uses a unified PHP backend to serve both a high-performance REST API and a Server-Side Rendered (SSR) marketing frontend. This structure ensures optimal SEO for public pages while maintaining a reactive Single Page Application (SPA) experience for the client and admin dashboards.

### System Diagram (Mermaid)

```mermaid
graph TD
    User[User / Client] -->|HTTPS| CDN[CDN / Load Balancer]
    CDN --> WebServer[Nginx / Apache]
    
    subgraph "Public Zone (Marketing)"
        WebServer -->|Route: /| SEO[SEO Injector (index.php)]
        SEO --> ThemeEngine[Theme Engine (PHP)]
        ThemeEngine --> CMSdb[(Pages & Settings JSONB)]
    end

    subgraph "App Zone (React SPA)"
        WebServer -->|Route: /admin, /portal| ReactApp[React App Shell]
        ReactApp -->|Fetch API| API[PHP REST API]
    end

    subgraph "Core Backend Services"
        API --> Auth[Auth Controller]
        API --> Uploads[File Security & Thumbnails]
        API --> Payments[Stripe Integration]
        API --> Email[Email Service]
        API --> Config[Config Helper]
    end

    subgraph "Data Persistence"
        Auth --> DB[(PostgreSQL)]
        Uploads --> DB
        Payments --> DB
        
        Auth --> AuthTokens[Auth Tokens Table]
        API --> DownloadTokens[Download Tokens Table]
        
        Uploads --> Storage[Secure Storage (Local/S3)]
    end

    Stripe[Stripe Webhook] -->|POST| API

```

---

## 2. Core Class Structure & Feature Mapping

This section maps specific PHP classes and methods to the end-user features they implement.

### A. Core Engine & Utilities

| Class | Method | Feature / Responsibility |
| --- | --- | --- |
| **`ThemeEngine`** | `renderPage($slug, $tree)` | **CMS Rendering:** Recursively renders the JSON block tree into HTML. Handles nested blocks (`[blocks-container]`) and shortcodes. |
|  | `getAvailableBlocks()` | **Page Builder:** Scans the `themes/` directory to tell the Admin UI which blocks exist. |
| **`FileSecurity`** | `processUpload($file)` | **Secure Uploads:** Validates MIME types via magic bytes, sanitizes filenames, extracts EXIF data, and generates thumbnails. |
|  | `extractExif($path)` | **Metadata:** Reads ISO, Lens, and Camera data for the Admin Inspector. |
| **`EmailService`** | `send($to, $template, $data)` | **Notifications:** Fetches HTML templates from DB, injects branding (Logo/Colors), wraps content, and sends via SMTP. |
| **`ConfigHelper`** | `getTimeout()` | **Configuration:** Retreives the global timeout setting (e.g., 10 mins) for magic links and downloads. |

### B. Controllers (API Layer)

| Class | Method | Feature / Responsibility |
| --- | --- | --- |
| **`AuthController`** | `requestLink()` | **Login:** Generates a secure login token with a configured expiration time. |
|  | `verifyLink()` | **Session:** Validates the token hash and initiates the PHP session. |
| **`CmsController`** | `savePage()` | **Page Builder:** Validates and stores the JSON block tree from the React Admin UI. |
| **`DownloadController`** | `generateLink($id)` | **Secure Downloads:** Creates a one-time-use signed URL for downloading large ZIP files. |
|  | `streamZip()` | **Performance:** Streams the ZIP creation directly to the output buffer to avoid memory exhaustion. |
| **`FileController`** | `view($id)` | **IDOR Protection:** The "Gatekeeper" proxy. Verifies ownership before serving protected images from storage. |
| **`StripeHandler`** | `handle()` | **Payments:** Validates Stripe webhooks signatures and idempotency keys to prevent fraud. |

---

## 3. Key Workflows

### A. The "Theme Engine" Rendering Pipeline

*Goal: Serve dynamic, SEO-friendly marketing pages without React hydration lag.*

1. **Request:** User visits `yourdomain.com/about`.
2. **Routing:** `index.php` intercepts the request.
3. **Lookup:** Checks `pages` table for `slug = 'about'`.
4. **Injection:** * Fetches Global SEO settings (Title, OG Image) from `settings` table.
* Injects `<meta>` tags into the HTML head before output.


5. **Composition:** * Initializes `ThemeEngine`.
* Loads the `master` layout file (`themes/clarity_default/layouts/master/view.php`).
* Parses the JSON Block Tree for the page.
* Recursively renders blocks (e.g., `Hero`, `Text`, `ContactForm`).


6. **Output:** Returns fully formed HTML string.

### B. Secure File Upload & Processing

*Goal: Prevent RCE attacks and handle large photography files efficiently.*

1. **Upload:** Admin drags file to React Dropzone.
2. **Validation (API Layer):**
* Checks PHP `upload_max_filesize`.
* **Magic Byte Check:** Verifies binary signature matches allowed MIME types (JPG, PNG, HEIC).


3. **Processing:**
* **Sanitization:** Original filename is discarded. A UUID + Hex Hash is generated.
* **Extraction:** EXIF data (Camera, Lens, ISO) is read via `FileSecurity::extractExif`.
* **Optimization:** `GD` library generates a 400px thumbnail.


4. **Storage:**
* Files are moved to `/storage_secure/` (Outside public webroot).
* Database records the logical path (`project_id/hash.jpg`).



### C. The Secure Download Workflow (Signed URLs)

*Goal: Securely deliver large assets without exposing storage paths or crashing the browser.*

1. **Request:** Client clicks "Download ZIP".
2. **Generation:** * `DownloadController::generateLink` checks permissions (Paid status).
* Generates a random token and hashes it.
* Stores hash in `download_tokens` with an expiration (default 10 mins).
* Returns a URL: `/api/download/stream?token=xyz`.


3. **Redirect:** Frontend redirects `window.location` to this URL.
4. **Streaming:**
* `DownloadController::streamZip` validates the token hash and expiration.
* Token is deleted (One-time use).
* ZIP archive is built on-the-fly and streamed to the browser.



---

## 4. Data Model (PostgreSQL)

We utilize PostgreSQL's relational strengths for integrity and JSONB for flexibility.

| Table | Key Responsibility | JSONB Usage |
| --- | --- | --- |
| `users` | Auth & RBAC | N/A |
| `projects` | Project Containers | `package_snapshot` (Stores price/features at time of booking) |
| `photos` | Asset References | `metadata` (EXIF data: ISO, Lens, etc.) |
| `settings` | Config & Secrets | `public_config` (Branding, Timeouts), `private_config` (API Keys) |
| `pages` | CMS Content | `blocks` (The recursive tree of UI components) |
| `download_tokens` | Secure Links | N/A (Stores Hash & Expiry) |
| `email_templates` | Notifications | N/A |

---

## 5. Security Implementation Details

### Trust Boundaries

1. **Public Zone:** Can only access `GET /api/cms/*` and `POST /api/auth/magic-link`.
2. **Client Zone:** Requires valid Session Cookie. Can only access resources linked to their `email`.
3. **Admin Zone:** Requires `role = 'admin'`. Full access.

### IDOR Protection (The Proxy)

Direct file access is blocked at the Nginx/Apache level. All image requests go through:
`GET /api/files/view/{uuid}`

```php
// Pseudo-code logic
function view($photoId) {
    $photo = DB::find($photoId);
    $user = CurrentUser();
    
    if ($user->role !== 'admin' && $user->email !== $photo->project->client_email) {
        throw new ForbiddenException();
    }
    
    // Serve file securely
    $storage->output($photo->system_filename);
}

```

```

```
