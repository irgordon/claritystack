# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.21] - 2026-02-07

### Performance
- **ProjectGallery**: Implemented `useMemo` for `itemData` prop passed to `react-window` grid.
    - **What**: Wrapped the object containing `chunks`, `chunkOffsets`, and `totalCount` in `useMemo`.
    - **Why**: The object was being recreated on every render (e.g., when updating page number state), causing `react-window` to perform unnecessary re-renders of all visible cells even when the underlying photo data had not changed.
    - **Measured Improvement**: Simulated benchmark showed a ~1000x speedup in render cycle cost (reduction from ~5325ms to ~5ms for 1000 iterations) when avoiding unnecessary grid updates.

## [1.0.20] - 2026-02-07

### Performance
- **RateLimiter**: Optimized initialization to avoid redundant DDL checks on every request.
    - **What**: Added a file existence check before executing `CREATE TABLE` and `PRAGMA journal_mode`.
    - **Why**: Redundant DDL execution adds unnecessary overhead (file locking/parsing) to every request.
    - **Measured Improvement**: Micro-benchmarks show a ~50% reduction in initialization overhead (from ~0.098ms to ~0.042ms per new connection).

## [1.0.19] - 2026-02-07

### Performance
- **Email Queue**: Replaced process forking with sequential processing in `process_email_queue.php`.
    - **What**: Removed `pcntl_fork` and implemented a sequential loop with database connection reuse.
    - **Why**: Process forking created significant memory overhead (multiplying memory footprint by the number of concurrent emails), which could lead to OOM errors on resource-constrained servers.
    - **Measured Improvement**: Benchmark with 20 concurrent emails showed a ~95% reduction in peak memory usage (from ~42 MB to ~2 MB).

## [1.0.18] - 2026-02-07

### Performance
- **ThemeEngine**: Implemented caching for `purifyHtml` results.
    - **What**: Added an in-memory cache keyed by the MD5 hash of the input HTML to store sanitized results.
    - **Why**: `purifyHtml` uses `DOMDocument::loadHTML`, which is expensive when processing the same content repeatedly.
    - **Measured Improvement**: Benchmark showed a ~3x speedup (reduction from ~0.39s to ~0.13s for 5000 iterations) for repeated content.

## [1.0.17] - 2026-02-07

### Performance
- **ThemeEngine**: Implemented Full Page Caching.
    - **What**: Caches the fully rendered HTML output of pages to the filesystem, using a cache key based on the layout, block content, and global settings timestamp.
    - **Why**: Rendering pages is CPU-intensive due to recursive block processing and configuration parsing.
    - **Measured Improvement**: Benchmark showed a ~10.9x speedup (reduction from ~17.5ms to ~1.6ms per page) for cached requests.

## [1.0.16] - 2026-02-07

### Performance
- **ProjectGallery**: Optimized photo list storage by replacing flat array concatenation with a chunked array structure.
    - **What**: Replaced flat array state with an array of arrays (chunks) to avoid O(N) copying during updates.
    - **Why**: Eliminates performance degradation when loading many pages of photos.
    - **Measured Improvement**: Benchmark showed reduction from ~765ms to ~27ms for appending 100k items.

## [1.0.15] - 2026-02-07

### Performance
- **ProjectController**: Refactored implicit database join to explicit `CROSS JOIN` syntax in authorization query.
    - **What**: Replaced comma-separated table list with explicit `CROSS JOIN`.
    - **Why**: Improves code clarity and adherence to modern SQL standards.
    - **Measured Improvement**: Benchmark with 50k iterations showed negligible performance difference (+/- 3% noise), confirming no regression while improving maintainability.

## [1.0.14] - 2026-02-07

### Performance
- **Email Queue**: Implemented parallel processing using `pcntl_fork` in `process_email_queue.php`. This allows emails to be sent concurrently (limited by batch size), significantly reducing the time to process the queue when facing network latency (e.g., mail server delays).
    - **Benchmark**: Processing 5 emails with 1s latency each improved from ~5s (serial) to ~1.1s (parallel).

## [1.0.13] - 2026-02-07

### Performance
- **Benchmarks**: Added `tests/bench_array_concat.js` to verify and prevent regression of the ProjectGallery state update optimization.

## [1.0.12] - 2026-02-07

### Performance
- **RateLimiter**: Replaced file-based I/O with SQLite (using WAL mode), improving request throughput by ~33% (from ~9.3k to ~12.4k ops/sec) and preventing file system exhaustion during high traffic.

## [1.0.11] - 2026-02-07

### Performance
- **ThemeEngine**: Optimized `purifyHtml` by replacing `DOMXPath` with native `getElementsByTagName` loop, reducing memory allocation and execution overhead. Benchmarks show a ~10% improvement in rendering performance for content blocks.

## [1.0.10] - 2026-02-07

### Performance
- **ProjectGallery**: Optimized photo list state updates by replacing array spread with `.concat()`, improving performance for large datasets (~5x faster for 100k items).

## [1.0.9] - 2026-02-06

### Security
- **DownloadController**: Patched a broken access control vulnerability in `generateLink` that allowed downloading unpaid projects.
    - **What**: Enforced payment status verification before generating download tokens.
    - **Why**: To prevent unauthorized access to unpaid deliverables.
    - **How**: Added logic to validate `session_id`, verify project ownership, and check payment status (Paid/Free/Admin-override) before proceeding.

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
