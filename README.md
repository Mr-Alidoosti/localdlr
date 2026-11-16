# LocalDLR — Modern PHP Download Center & File Manager

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/TailwindCSS-v3.x-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/SQLite-Database-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/i18n-EN%20%7C%20FA-success?style=flat-square" alt="Multilingual">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="License">
</p>

**LocalDLR** is a lightweight, responsive, and self-hosted PHP file explorer and download center. Built with **Tailwind CSS** (inspired by Keenthemes Metronic Demo 1), it provides fast folder-by-folder AJAX browsing, real-time download tracking, Light & Dark themes, bilingual RTL/LTR support (English & Persian) with cookie persistence, in-browser file previews, and enterprise-grade security.

---

## ✨ Features

- **🌐 Bilingual & Dual-Direction (EN / FA with RTL & LTR)**
  - Seamless one-click language switcher (`EN | FA`) available in both header and footer.
  - Automatic layout mirroring: **RTL** (`dir="rtl"`) for Persian and **LTR** (`dir="ltr"`) for English.
  - Native calendar localization: **Jalali / Solar Hijri** dates for Persian and **Gregorian** dates for English.
  - Localized file size units (`KB, MB, GB` vs `کیلوبایت، مگابایت، گیگابایت`) and numbers.
  - Stored in a browser cookie (`lang=fa|en`) with 365-day expiry and zero FOUC on reload.
  - Clean dictionary-based structure (`lang/fa.php` & `lang/en.php`) for easy expansion.
- **⚡ Folder-by-Folder AJAX Navigation**
  - Instant directory navigation without full page reloads.
  - Full browser history synchronization (`pushState` and `popstate` support for Back/Forward navigation).
  - Dynamic interactive breadcrumbs and dedicated "Back to parent folder" button with auto-flipping directional icons.
- **📂 Unified Explorer Layout**
  - Subfolders and files rendered cohesively in table rows or grid cards.
  - Supports unlimited nested directory depth inside the `files/` root.
- **🌓 Light & Dark Theme**
  - Fully styled with Metronic Dark tokens (`#15171C`, `#1E2129`, `#262B36`).
  - Stored in a browser cookie (`theme=light|dark`) with 1-year persistence.
  - **Zero FOUC**: Server-side theme detection renders `<html class="dark">` immediately.
- **📊 Robust Download Tracking**
  - SQLite database (`data/downloads.sqlite`) with concurrency locking and JSON fallback.
  - Instant UI counter updates for immediate visual feedback.
  - **Accurate counts**: File previews (`preview=1`) and HTTP Range resume chunks do not trigger duplicate counts.
- **🔍 Real-Time Search & Category Filters**
  - Quick filename search with keyboard shortcut (`Ctrl + K` / `Cmd + K`).
  - Instant filtering by file category: Documents, Software, Archives, Images, Video, Audio, and Code.
- **🗂️ Multi-Criteria Sorting**
  - Sort by latest modified date, most downloaded, file size (ascending/descending), or alphabetically (A–Z).
- **👁️ In-Browser Preview Modal**
  - Preview images (`.png`, `.jpg`, `.webp`, `.svg`, `.gif`), documents (`.pdf`), video (`.mp4`, `.webm`), and audio (`.mp3`, `.wav`) without leaving the page.
- **🔗 Direct Download & Link Sharing**
  - 1-click download link copying with animated toast notifications.
  - Resumable chunked file streaming with HTTP `Range` header support.
- **📦 100% Offline & Self-Hosted**
  - Zero external CDN dependencies. All CSS, JavaScript, Tailwind runtime, variable fonts (IRANSansX), and SVG icons are hosted entirely within the local repository.
- **🔒 Security Hardened**
  - Directory traversal prevention (`realpath`, `basename`, and root jail enforcement).
  - Pre-configured `.htaccess` rules in `data/` and `files/` blocking script execution and direct database downloads.

---

## 📁 Project Structure

```text
localdlr/
├── index.php             # Main dashboard UI (Metronic Tailwind, AJAX controller, KPIs)
├── download.php          # Secure streaming endpoint, HTTP Range handler & download counter
├── api.php               # JSON REST API for folder listings, breadcrumbs & stats
├── files/                # Root storage directory for your files and subdirectories
│   ├── documents/        # Example folder (PDFs, Word documents, spreadsheets)
│   ├── software/         # Example software directory (Windows, macOS builds)
│   ├── images/           # Example image assets
│   └── archives/         # Example compressed archives (.zip, .rar, .tar.gz)
├── assets/
│   ├── css/
│   │   └── style.css     # Custom animations, dark mode overrides & typography
│   ├── js/
│   │   ├── app.js        # Core frontend logic (AJAX navigation, search, sort, modals, cookies)
│   │   └── tailwindcss.js # Standalone local Tailwind CSS engine (100% offline)
│   └── fonts/            # Variable font files (IRANSansX)
├── lang/                 # Localization & language packs
│   ├── en.php            # English language dictionary
│   └── fa.php            # Persian (Farsi) language dictionary
├── includes/
│   ├── scanner.php       # Directory scanner, MIME resolver, Jalali/Gregorian dates & sizes
│   └── tracker.php       # Download count storage manager (SQLite PDO + JSON fallback)
├── data/
│   ├── .htaccess         # Blocks HTTP access to database files
│   └── downloads.sqlite  # SQLite database storing download counts
├── .gitignore
├── README.md
└── LICENSE
```

---

## 🚀 Getting Started

### Prerequisites

- **PHP 7.4+** or **PHP 8.0+** (PHP 8.2+ recommended)
- `pdo_sqlite` extension enabled (standard on most PHP installations)
- Read/write permissions on `data/` and `files/`

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Mr-Alidoosti/localdlr.git
   cd localdlr
   ```

2. **Ensure directory permissions:**
   ```bash
   chmod 775 data files
   ```

3. **Start the PHP built-in web server:**
   ```bash
   php -S localhost:8000
   ```

4. **Open in your browser:**
   Navigate to [http://localhost:8000](http://localhost:8000).

---

## ⚙️ Configuration & Customization

### Adding Files
Simply drop your files or nested directories into the `files/` folder. The scanner automatically detects new items, computes folder statistics, and formats human-readable file sizes.

### Language & Localization
- Language preference is stored in the `lang` cookie (`fa` or `en`).
- All user-facing strings are stored in `lang/fa.php` and `lang/en.php`.
- To add a new language, create a new dictionary file under `lang/{code}.php` and register it in `index.php` and `api.php`.

### Supported File Categories
Categories and badge styles are defined in `includes/scanner.php`:
| Category | Extensions |
| :--- | :--- |
| **Document** | `.pdf`, `.doc`, `.docx`, `.xls`, `.xlsx`, `.ppt`, `.pptx`, `.txt`, `.csv` |
| **Archive** | `.zip`, `.rar`, `.7z`, `.tar`, `.gz`, `.bz2`, `.iso` |
| **Software** | `.exe`, `.msi`, `.dmg`, `.pkg`, `.deb`, `.rpm`, `.apk`, `.appimage` |
| **Image** | `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`, `.svg`, `.bmp`, `.ico` |
| **Video** | `.mp4`, `.mkv`, `.avi`, `.mov`, `.webm`, `.flv` |
| **Audio** | `.mp3`, `.wav`, `.ogg`, `.flac`, `.m4a` |
| **Code** | `.js`, `.ts`, `.php`, `.py`, `.json`, `.html`, `.css`, `.sql`, `.sh` |

### Database Storage
Download counts are stored in `data/downloads.sqlite`. If SQLite is unavailable or disabled, the application automatically falls back to `data/downloads.json` with file locking (`LOCK_EX`).

---

## 🔒 Security

- **Path Traversal Protection**: All file requests are verified against `realpath(__DIR__ . '/files')`. Requests containing `..`, null bytes, or paths resolving outside `files/` are rejected with HTTP 403.
- **Direct Execution Block**: `.htaccess` files inside `files/` and `data/` disable PHP engine execution (`php_flag engine off`) and deny access to script extensions (`.php`, `.phtml`, `.sqlite`).
- **Safe Streaming**: Files are served in 64 KB chunks with memory limit safety and output buffer flushing.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
