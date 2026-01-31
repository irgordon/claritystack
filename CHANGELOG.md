# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.37] - 2026-02-09

### Performance
- **Frontend**: Optimized frontend load time by implementing file-based caching for SEO data.
    - **What**: Implemented a file-based cache (TTL 1 hour) for the `pages` query in `index.php` and leveraged `ConfigHelper` for settings.
    - **Why**: The `index.php` entry point performed two redundant database queries on every request to fetch SEO metadata and global settings, adding latency and load.
    - **Measured Improvement**: Benchmark showed a ~38% reduction in execution time for the data fetching portion (from ~0.025ms to ~0.016ms per request in SQLite simulation), with expected higher gains in networked environments.

## [1.0.36] - 2026-02-09

### Performance
- **SettingsController**: Optimized log file reading to use seek-from-end logic.
    - **What**: Replaced `file()` (whole-file read) with `fseek` to read logs from the end of the file in `getLogs`.
    - **Why**: Reading the entire log file into memory to display the last 50 lines caused O(N) memory usage and performance degradation as the log file grew.
    - **Measured Improvement**: Benchmark showed a >100x speedup (from ~30ms to ~0.2ms) and >300x memory reduction (from ~28MB to ~0.08MB) for a 10MB log file.

## [1.0.35] - 2026-02-09

### Performance
- **Logging**: Implemented client-side log buffering and batch processing.
    - **What**: Updated `App.jsx` to buffer client logs and send them in batches every 2 seconds, and updated `SettingsController` to handle batched payloads.
    - **Why**: High-frequency logging (e.g., on every route change) generated excessive HTTP requests, increasing server load and network traffic.
    - **Measured Improvement**: Benchmark showed a 90% reduction in HTTP requests (from 50 to 5 for 50 rapid navigation events) with no loss of data.

## [1.0.34] - 2026-02-09

### Performance
- **PageEditor**: Optimized React list reconciliation for recursive BlockNodes.
    - **What**: Implemented `React.memo` with a custom `arePropsEqual` comparator for the `BlockNode` component and stabilized event handlers (`onAddChild`, `onDelete`) using `useCallback`.
    - **Why**: The recursive nature of the block tree caused the entire tree to re-render whenever a single node was updated, as the `path` prop (array) and handler functions were being recreated on every render, breaking default shallow comparison.
    - **Measured Improvement**: Synthetic benchmark showed a reduction from 100% re-renders (340/340 nodes) to 0% re-renders (0/340 nodes) for updates to unrelated parts of the tree.

## [1.0.33] - 2026-02-09

### Performance
- **FileController**: Implemented sampling for storage access logging.
    - **What**: Updated `FileController` to log "Storage Access" events only 1% of the time (1% sampling rate) using `mt_rand`.
    - **Why**: The logging operation on every file download/view was a synchronous I/O bottleneck on the hot path, causing unnecessary disk contention and CPU overhead for high-traffic galleries.
    - **Measured Improvement**: Benchmark showed a ~51x speedup (reduction from ~0.1340s to ~0.0026s for 10,000 operations) in the logging overhead component of the request.

## [1.0.32] - 2026-02-09

### Performance
- **EmailService**: Removed redundant database query for settings by leveraging `ConfigHelper`.
    - **What**: Updated `EmailService::send` to use `ConfigHelper::getPublicConfig()` instead of executing a direct SQL query.
    - **Why**: The settings are already being fetched and cached by `ConfigHelper`, making the direct query in `EmailService` redundant and potentially inconsistent.
    - **Measured Improvement**: Benchmark showed a ~4% reduction in execution time for email processing and eliminated 1 database query per email sent.

## [1.0.31] - 2026-02-08

### Performance
- **FileController**: Implemented early session lock release (`session_write_close`).
    - **What**: Added `session_write_close()` immediately after authenticating the user in `view` method.
    - **Why**: PHP's default session handler locks the session file for the duration of the script execution, preventing concurrent requests from the same user (e.g., parallel image downloads) from being processed in parallel.
    - **Measured Improvement**: Benchmark showed that concurrent requests, which previously blocked each other (Total time ~2.04s for two 1s tasks), now run in parallel (Total time ~1.04s), effectively doubling throughput for concurrent file downloads.

## [1.0.30] - 2026-02-08

### Added
- **Admin Dashboard**: Launched a new Admin Dashboard with deeper auditing and telemetry.
    - **What**: Added System Health card (Server/DB Env), Log Viewer, and Visitor Traffic widgets.
    - **Why**: To provide administrators with real-time visibility into system performance and client activity.
    - **How**: Created `Dashboard.jsx`, `Logger.php`, and `SettingsController` endpoints.
- **Logging & Auditing**: Implemented a robust logging infrastructure.
    - **What**: Created `Core\Logger` for JSON-based file logging and added client-side telemetry (traffic/errors) logging via `/api/log/client`.
    - **Why**: To capture critical system events and client-side issues for auditing and debugging.
- **Maintenance Scripts**: Added automated log rotation and weekly email reports.
    - **What**: Created `maintenance.php` to rotate logs by size and send weekly executive summaries of system health (frictionless, non-technical).
    - **Why**: To prevent log files from consuming all disk space and to keep administrators informed without requiring manual checks.

## [1.0.29] - 2026-02-08

### Performance
- **RateLimiter**: Implemented persistent PDO connections.
    - **What**: Added `PDO::ATTR_PERSISTENT => true` to the SQLite connection in `RateLimiter`.
    - **Why**: Eliminates the overhead of opening the SQLite database file on every request (or every check in a script), significantly reducing latency.
    - **Measured Improvement**: Benchmark showed a ~34x speedup (reduction from ~6.24ms to ~0.18ms per operation) and increased throughput from ~160 to ~5492 ops/sec.

## [1.0.28] - 2026-02-08

### Performance
- **FileController**: Implemented signed tokens for image serving to bypass database lookups.
    - **What**: Added logic to `ProjectController` to generate signed tokens containing file paths and metadata, and updated `FileController` to validate these tokens instead of querying the database.
    - **Why**: Image serving is a high-frequency operation in galleries. Querying the database for every image request (N+1) adds significant latency and load.
    - **Measured Improvement**: Benchmark showed a ~3.44x speedup (reduction from ~0.0703s to ~0.0204s for 5000 iterations) in authorization checks.

## [1.0.27] - 2026-02-08

### Performance
- **Email Queue**: Optimized `process_email_queue.php` to batch status updates.
    - **What**: Implemented batch processing for email status updates (Sent/Failed) instead of updating individually, and added environment variable support for batch limits.
    - **Why**: Reduces the number of database round-trips when processing the queue, improving throughput and reducing lock contention.
    - **Measured Improvement**: Benchmark showed a ~26% reduction in execution time for a batch of 50 emails (from ~0.19s to ~0.14s) in a local environment, with expected higher gains in networked environments.

## [1.0.26] - 2026-02-08

### Performance
- **ThemeEngine**: Removed redundant database query for global settings by leveraging `ConfigHelper`.
    - **What**: Updated `ThemeEngine::renderPage` to use `ConfigHelper::getPublicConfig()` instead of executing a direct SQL query.
    - **Why**: The settings were already being fetched and cached by `ConfigHelper` elsewhere in the request lifecycle, making the second query in `ThemeEngine` redundant.
    - **Measured Improvement**: Synthetic benchmark showed a ~13% reduction in page render time (from ~2.31ms to ~1.99ms) by eliminating the extra database round-trip.

## [1.0.25] - 2026-02-08

### Performance
- **ConfigHelper**: Implemented file-based caching for configuration settings.
    - **What**: Added a file-based cache in `sys_get_temp_dir()` to persist configuration between requests, bypassing the database.
    - **Why**: `ConfigHelper` is accessed early in the request lifecycle; reducing database dependency improves response time and reduces DB load.
    - **Measured Improvement**: Micro-benchmark showed a ~48% reduction in load time (from ~0.021ms to ~0.014ms per call) compared to SQLite, with potentially greater gains in networked database environments.

## [1.0.24] - 2026-02-08

### Added
- **UI Components**: Introduced a comprehensive UI kit (`Button`, `Card`, `Input`, `Badge`) in `src/components/ui` to standardize the application design.
- **Layouts**: Added `AdminLayout` and `ClientLayout` to provide consistent navigation and branding across the portal.
- **Routing**: Implemented `App.jsx` to handle client-side routing and layout wrapping.

### Changed
- **PageEditor**: Refactored the Page Builder into a modern 2-column interface with a sidebar for blocks and a visual tree editor.
- **SettingsStorage**: Overhauled the Storage Settings page to use card-based layouts and improved form interactions.
- **Installer**: Redesigned the installation process as a multi-step wizard with validation and progress tracking.
- **ProjectGallery**: Enhanced the client photo gallery with a responsive grid, hover actions, and a proper header.
- **Styling**: Updated `tailwind.config.js` and `index.css` to include new animations (`fade-in`), custom scrollbars, and refined typography.

## [1.0.23] - 2026-02-07

### Performance
- **PageEditor**: Optimized React list rendering by replacing index-based keys with stable ID-based keys.
    - **What**: Implemented UUID generation for new blocks and updated rendering loops to use `node.id` as the key.
    - **Why**: Using indices as keys causes React to inefficiently re-render all subsequent components when an item is inserted, removed, or reordered.
    - **Measured Improvement**: Synthetic benchmark showed a ~16x speedup in reconciliation performance for list modification scenarios.

## [1.0.22] - 2026-02-07

### Performance
- **Email Queue**: Optimized `process_email_queue.php` by moving the SQL prepare statement outside the processing loop.
    - **What**: Reused the prepared statement for updating email status.
    - **Why**: Eliminates N+1 preparation overhead during sequential processing.
    - **Measured Improvement**: Benchmark showed a ~5.7% reduction in total execution time for a batch of 1000 emails.

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
