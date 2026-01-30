# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Performance
- **ThemeEngine**: Implemented `blockFileCache` to cache resolved view file paths, reducing repeated filesystem checks during block rendering. Benchmarks show a ~15% improvement in execution time for repeated block calls.

## [1.0.8] - 2026-02-06

### Performance
- **Email Queue**: Optimized `process_email_queue.php` to prepare SQL statements once outside the loop, eliminating the N+1 query preparation overhead. Benchmarks show a ~78% reduction in database interaction time for bulk updates.
- **ProjectGallery**: Implemented virtualization using `react-window` and `react-virtualized-auto-sizer` to efficiently render large lists of photos, significantly reducing DOM nodes and improving scrolling performance.
- **ThemeEngine**: Replaced Tailwind Play CDN with pre-compiled CSS in `clarity_default` theme, reducing external network requests and eliminating runtime compilation overhead.

### Security
- **ProjectController**: Fixed a critical vulnerability where `listPhotos` was accessible without authentication. Added session verification and ownership checks (IDOR protection).

## [1.0.7] - 2026-02-05

### Performance
- **EmailService**: Implemented request-scoped caching for `settings` and `email_templates`, reducing database round-trips from 3 to 1 per email. Benchmarks show a ~63% reduction in processing time for bulk operations.

## [1.0.6] - 2026-02-04

### Fixed
- **Installer Reliability**: Added comprehensive environment sanity checks (PHP version, extensions, permissions) to the installer to fail fast if requirements are not met.
- **Data Integrity**: Wrapped the installation process in a database transaction to ensure atomicity; failures during installation now cleanly rollback any partial database changes.

## [1.0.5] - 2026-02-03

### Performance
- **Email System**: Transformed `EmailService` to use an asynchronous queue backed by PostgreSQL (`email_queue` table).
- **Background Processing**: Added `api/scripts/process_email_queue.php` to handle email dispatching, preventing web request blocking and timeout issues during bulk sends or slow SMTP connections.

## [1.0.4] - 2026-02-02

### Performance
- **Database**: Implemented Singleton pattern for the `Database` class to eliminate redundant connections within a single request.
- **Optimization**: Updated all controllers and services to use `Database::getInstance()->connect()`, ensuring a shared database connection is reused, significantly reducing connection overhead.

## [1.0.3] - 2026-01-30

### Performance
- **ThemeEngine**: Optimized `purifyHtml` to reuse `DOMDocument` instances, significantly reducing object allocation overhead during recursive block rendering.
- **FileController**: Added 'Cache-Control' headers to image responses to improve load times and reduce bandwidth.
- **ConfigHelper**: Implemented request-lifecycle caching for `getTimeout()` to eliminate repeated database queries, reducing overhead by ~99% for multiple calls.

### Security
- **Hardcoded Secrets**: Removed hardcoded encryption key from `Core\Security` and implemented secure loading from `env.php`.
- **Directory Traversal**: Implemented strict path validation in `LocalAdapter` to prevent directory traversal attacks.
- **Rate Limiting**: Refactored `AuthController` to use the centralized `Core\RateLimiter` class, ensuring consistent protection against brute force attacks.

### Refactor
- **Directory Structure**: Consolidated storage adapters into `clarity_app/api/core/Storage/` and removed duplicate `api/` directory.
- **Storage**: Introduced `StorageInterface` and `LocalAdapter` to standardize file operations across different providers.

## [1.0.0] - 2026-01-29

### 🚀 Initial Release
**ClarityStack v1.0.0** is the first stable release of the hybrid CMS and Client Proofing Platform. This release establishes the core architecture, security protocols, and theming engine.

### Added
- **Core Architecture**
  - Implemented **Hybrid Monolith** structure: PHP 8.2 Backend + React 18 Frontend.
  - Added `ThemeEngine.php`: A recursive, file-based rendering engine for server-side layouts.
  - Added `BrandingContext.jsx`: Dynamic frontend theming (Colors, Fonts, Radius) injected at runtime via CSS Variables.

- **CMS & Page Builder**
  - Introduced `[blocks-container]` logic for nested block rendering (e.g., Columns inside Sections).
  - Added "Safe Mode" rendering: Block-level errors are caught and logged without crashing the entire page.
  - Added **Clarity Default Theme**: A fully responsive, Tailwind-based photography theme with a "Master Layout" architecture.

- **Security & Auth**
  - **Magic Links**: Passwordless email authentication for clients with configurable timeouts (Default: 10 mins).
  - **IDOR Protection**: `FileController.php` acts as a secure proxy, enforcing ownership checks before streaming files from `/storage_secure`.
  - **Secure Downloads**: Large ZIP generation uses signed, one-time-use URLs (`/api/download/stream?token=...`) to prevent memory exhaustion and unauthorized sharing.
  - **Installer**: Automated setup wizard (`/install`) that generates `env.php`, migrates the DB schema, and creates the Super Admin.

- **Studio Management**
  - **EXIF Extraction**: Automatically pulls Camera, Lens, and ISO data from uploaded images.
  - **Admin Dashboard**: System Health logs, Social Media configuration, and Gallery Management.
  - **Dynamic Footer**: Social media links in the footer are now dynamically fetched from the database settings.

### Changed
- **Directory Structure**: Moved `api`, `database`, and `themes` outside of the public web root for enhanced security.
- **Routing**: Updated `.htaccess` to strictly route API and React requests to `index.php` while serving assets directly.
- **Versioning Policy**: Adopted semantic versioning:
  - `1.x.x`: Major architectural changes / Breaking API changes.
  - `1.0.x`: Minor features, bug fixes, and security patches.

### Fixed
- Resolved recursion depth issues in the Page Builder by adding a `MAX_RECURSION_DEPTH` guard.
- Fixed social media links in the default theme footer (previously hardcoded to `#`).
- Fixed database connection logic to gracefully handle "First Run" scenarios before `env.php` exists.

### Security
- **Strict Trust Boundaries**: Public (Marketing), Client (Portal), and Admin zones are logically and physically separated.
- **Sanitization**: All user inputs (Admin CMS and Installer) are sanitized via `htmlspecialchars` or prepared PDO statements.
