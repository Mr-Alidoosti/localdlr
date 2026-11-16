<?php
/**
 * CDN File Manager & Download Center
 * Styled with Metronic Tailwind Demo 1 & IRANSansX Variable Font.
 */

require_once __DIR__ . '/includes/tracker.php';
require_once __DIR__ . '/includes/scanner.php';

// Read language preference from cookie
$currentLang = $_COOKIE['lang'] ?? 'fa';
if (!in_array($currentLang, ['fa', 'en'], true)) {
    $currentLang = 'fa';
}
$isRtl = ($currentLang === 'fa');
$dir = $isRtl ? 'rtl' : 'ltr';

// Load language dictionary
$langFile = __DIR__ . '/lang/' . $currentLang . '.php';
$t = file_exists($langFile) ? require $langFile : require __DIR__ . '/lang/fa.php';

$scanner = new FileScanner(null, $currentLang);
$globalStats = $scanner->getGlobalStats();
$initialFolder = $_GET['folder'] ?? '';
$initialData = $scanner->scanFolder($initialFolder);

// Base URL detection
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$baseUri = rtrim(dirname($scriptName), '/\\');
$fullBaseUrl = $protocol . $host . ($baseUri ? '/' . ltrim($baseUri, '/') : '');

// Read theme preference from cookie
$theme = $_COOKIE['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $dir ?>" class="<?= $theme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['app_title']) ?></title>
    
    <!-- Local Tailwind CSS -->
    <script src="assets/js/tailwindcss.js"></script>
    
    <!-- Custom Styles & Variable Font (IRANSansX) -->
    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#F1F6FF',
                            100: '#DDE9FF',
                            500: '#1B84FF',
                            600: '#056EE9',
                            700: '#0053B8',
                        },
                        metronic: {
                            dark: '#1E2129',
                            body: '#F4F6F8',
                            card: '#FFFFFF',
                            border: '#F1F1F4',
                            text: '#4B5675',
                            heading: '#1E2129',
                            muted: '#99A1B7'
                        }
                    },
                    fontFamily: {
                        sans: ['IRANSansX', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
                    }
                }
            }
        }
    </script>
</head>
<body class="text-metronic-text dark:text-gray-300 antialiased min-h-screen flex flex-col bg-metronic-body dark:bg-[#15171C]">

    <!-- Top Navigation / Header -->
    <header class="bg-white dark:bg-[#1E2129] border-b border-metronic-border dark:border-[#262B36] sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <a href="index.php" onclick="event.preventDefault(); app.loadFolder('');" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-100 dark:border-brand-500/20 flex items-center justify-center text-brand-500 shadow-sm group-hover:bg-brand-500 group-hover:text-white transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-lg font-bold text-metronic-heading dark:text-white leading-tight group-hover:text-brand-600 transition-colors"><?= htmlspecialchars($t['brand_title']) ?></h1>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/60">
                                    <?= htmlspecialchars($t['status_online']) ?>
                                </span>
                            </div>
                            <p class="text-xs text-metronic-muted hidden sm:block"></p>
                        </div>
                    </a>
                </div>

                <!-- Global Search Bar -->
                <div class="flex-1 max-w-md mx-4 hidden md:block">
                    <div class="relative">
                        <input type="text" id="globalSearchInput" placeholder="<?= htmlspecialchars($t['search_placeholder']) ?>"
                            class="w-full <?= $isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4' ?> py-2 text-sm bg-metronic-body dark:bg-[#15171C] border border-gray-200 dark:border-[#262B36] rounded-xl focus:outline-none focus:border-brand-500 focus:bg-white dark:focus:bg-[#1E2129] transition-all text-metronic-heading dark:text-white placeholder-metronic-muted dark:placeholder-gray-500">
                        <div class="absolute inset-y-0 <?= $isRtl ? 'right-0 pr-3' : 'left-0 pl-3' ?> flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2">
                    <!-- Language Switcher (EN | FA) -->
                    <div class="flex items-center text-xs font-semibold rounded-xl border border-gray-200 dark:border-[#262B36] bg-gray-50 dark:bg-[#15171C] p-0.5" role="group" aria-label="Language">
                        <button type="button" onclick="app.setLanguage('en')" title="English"
                            class="px-2 py-1 rounded-lg transition-all <?= $currentLang === 'en' ? 'bg-white dark:bg-[#1E2129] text-brand-600 dark:text-brand-400 shadow-xs font-bold' : 'text-gray-500 dark:text-gray-400 hover:text-metronic-heading dark:hover:text-white' ?>">
                            EN
                        </button>
                        <span class="text-gray-300 dark:text-gray-600 select-none text-[10px]">|</span>
                        <button type="button" onclick="app.setLanguage('fa')" title="فارسی"
                            class="px-2 py-1 rounded-lg transition-all <?= $currentLang === 'fa' ? 'bg-white dark:bg-[#1E2129] text-brand-600 dark:text-brand-400 shadow-xs font-bold' : 'text-gray-500 dark:text-gray-400 hover:text-metronic-heading dark:hover:text-white' ?>">
                            FA
                        </button>
                    </div>

                    <!-- Light / Dark Mode Toggle Button -->
                    <button type="button" onclick="app.toggleTheme()" id="themeToggleBtn" title="<?= htmlspecialchars($t['theme_toggle']) ?>"
                        class="p-2 text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 hover:bg-brand-50 dark:hover:bg-gray-800 rounded-lg transition-colors border border-transparent hover:border-brand-100 dark:hover:border-gray-700">
                        <!-- Sun icon for dark mode (click to switch to light) -->
                        <svg class="w-5 h-5 hidden dark:block text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <!-- Moon icon for light mode (click to switch to dark) -->
                        <svg class="w-5 h-5 block dark:hidden text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>

                    <button type="button" onclick="app.loadFolder(app.currentFolder)" title="<?= htmlspecialchars($t['refresh']) ?>"
                        class="p-2 text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 hover:bg-brand-50 dark:hover:bg-gray-800 rounded-lg transition-colors border border-transparent hover:border-brand-100 dark:hover:border-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Top Statistics Cards (Metronic KPI Dashboard) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Files -->
            <div class="bg-white dark:bg-[#1E2129] rounded-2xl p-4 border border-metronic-border dark:border-[#262B36] shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-metronic-muted dark:text-gray-400 block mb-1"><?= htmlspecialchars($t['kpi_total_files']) ?></span>
                    <span class="text-2xl font-bold text-metronic-heading dark:text-white" id="kpiFilesCount"><?= number_format($globalStats['total_files']) ?></span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-brand-500 dark:text-brand-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>

            <!-- Total Storage -->
            <div class="bg-white dark:bg-[#1E2129] rounded-2xl p-4 border border-metronic-border dark:border-[#262B36] shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-metronic-muted dark:text-gray-400 block mb-1"><?= htmlspecialchars($t['kpi_total_storage']) ?></span>
                    <span class="text-2xl font-bold text-metronic-heading dark:text-white"><?= $globalStats['total_size_formatted'] ?></span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-500 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                </div>
            </div>

            <!-- Total Downloads -->
            <div class="bg-white dark:bg-[#1E2129] rounded-2xl p-4 border border-metronic-border dark:border-[#262B36] shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-metronic-muted dark:text-gray-400 block mb-1"><?= htmlspecialchars($t['kpi_total_downloads']) ?></span>
                    <span class="text-2xl font-bold text-metronic-heading dark:text-white" id="kpiTotalDownloads" data-count="<?= (int)$globalStats['total_downloads'] ?>"><?= number_format($globalStats['total_downloads']) ?></span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-500 dark:text-purple-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
            </div>

            <!-- Categories Count -->
            <div class="bg-white dark:bg-[#1E2129] rounded-2xl p-4 border border-metronic-border dark:border-[#262B36] shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-metronic-muted dark:text-gray-400 block mb-1"><?= htmlspecialchars($t['kpi_root_folders']) ?></span>
                    <span class="text-2xl font-bold text-metronic-heading dark:text-white"><?= $globalStats['total_folders'] ?></span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Explorer Area: Sidebar + File List -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

            <!-- Sidebar (Main Folders & Quick Filters) -->
            <div class="lg:col-span-1 space-y-4">
                
                <!-- Folders Navigation Card -->
                <div class="bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] shadow-sm p-4">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100 dark:border-[#262B36]">
                        <h2 class="text-sm font-bold text-metronic-heading dark:text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <?= htmlspecialchars($t['sidebar_main_folders']) ?>
                        </h2>
                        <span class="text-xs text-metronic-muted dark:text-gray-400"><?= count($globalStats['root_folders']) ?> <?= htmlspecialchars($t['sidebar_folders_count']) ?></span>
                    </div>

                    <div class="space-y-1">
                        <!-- Root/All Items Item -->
                        <button type="button" onclick="app.loadFolder('')" data-folder=""
                            class="sidebar-folder-btn w-full flex items-center justify-between px-3 py-2 text-sm rounded-xl transition-all font-medium <?= empty($initialFolder) ? 'bg-brand-50 dark:bg-brand-950/50 text-brand-600 dark:text-brand-400 font-semibold shadow-xs' : 'text-metronic-text dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/60' ?>">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <?= htmlspecialchars($t['sidebar_home']) ?>
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($t['sidebar_all']) ?>
                            </span>
                        </button>

                        <!-- Main Root Folders -->
                        <?php foreach ($globalStats['root_folders'] as $rf): ?>
                            <?php $isActive = ($initialFolder === $rf['path']); ?>
                            <button type="button" onclick="app.loadFolder('<?= htmlspecialchars($rf['path'], ENT_QUOTES) ?>')" data-folder="<?= htmlspecialchars($rf['path'], ENT_QUOTES) ?>"
                                class="sidebar-folder-btn w-full flex items-center justify-between px-3 py-2 text-sm rounded-xl transition-all font-medium <?= $isActive ? 'bg-brand-50 dark:bg-brand-950/50 text-brand-600 dark:text-brand-400 font-semibold shadow-xs' : 'text-metronic-text dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/60' ?>">
                                <span class="flex items-center gap-2.5 truncate">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    <span class="truncate <?= $isRtl ? 'dir-ltr text-right' : 'text-left' ?>"><?= htmlspecialchars($rf['name']) ?></span>
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $isActive ? 'bg-brand-100 dark:bg-brand-900/50 text-brand-700 dark:text-brand-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' ?>">
                                    <?= $rf['file_count'] ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Type Filters Card -->
                <div class="bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] shadow-sm p-4">
                    <h3 class="text-sm font-bold text-metronic-heading dark:text-white mb-3 pb-2 border-b border-gray-100 dark:border-[#262B36] flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <?= htmlspecialchars($t['filter_by_type']) ?>
                    </h3>
                    <div class="flex flex-wrap gap-1.5" id="typeFilterContainer">
                        <button type="button" onclick="app.filterByType('')" data-type=""
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border transition-all font-medium bg-brand-50 dark:bg-brand-950/50 text-brand-600 dark:text-brand-400 border-brand-200 dark:border-brand-800">
                            <?= htmlspecialchars($t['filter_all']) ?>
                        </button>
                        <button type="button" onclick="app.filterByType('document')" data-type="document"
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-medium">
                            <?= htmlspecialchars($t['filter_document']) ?>
                        </button>
                        <button type="button" onclick="app.filterByType('software')" data-type="software"
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-medium">
                            <?= htmlspecialchars($t['filter_software']) ?>
                        </button>
                        <button type="button" onclick="app.filterByType('archive')" data-type="archive"
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-medium">
                            <?= htmlspecialchars($t['filter_archive']) ?>
                        </button>
                        <button type="button" onclick="app.filterByType('image')" data-type="image"
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-medium">
                            <?= htmlspecialchars($t['filter_image']) ?>
                        </button>
                        <button type="button" onclick="app.filterByType('video')" data-type="video"
                            class="type-btn text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-medium">
                            <?= htmlspecialchars($t['filter_video']) ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main File List Area (Folder-by-Folder View) -->
            <div class="lg:col-span-3 space-y-5 relative min-h-[400px]">

                <!-- Loading Overlay -->
                <div id="folderLoadingOverlay" class="hidden absolute inset-0 bg-white/70 dark:bg-[#15171C]/80 backdrop-blur-[2px] z-20 rounded-2xl flex items-center justify-center">
                    <div class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white dark:bg-[#1E2129] shadow-xl border border-gray-100 dark:border-[#262B36]">
                        <div class="w-5 h-5 border-2 border-brand-500 border-t-transparent rounded-full animate-spin-slow"></div>
                        <span class="text-xs font-semibold text-metronic-heading dark:text-white"><?= htmlspecialchars($t['loading_folder']) ?></span>
                    </div>
                </div>

                <!-- Breadcrumbs & Toolbar -->
                <div class="bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] shadow-sm p-4">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                        
                        <!-- Breadcrumb & Title -->
                        <div>
                            <div class="flex items-center flex-wrap gap-1.5 text-xs text-metronic-muted dark:text-gray-400 mb-1" id="breadcrumbsContainer">
                                <!-- Dynamic breadcrumb inserted by JS -->
                            </div>
                            <h2 class="text-base font-bold text-metronic-heading dark:text-white flex items-center gap-2">
                                <span id="currentViewTitle"><?= htmlspecialchars($t['root_folder_title']) ?></span>
                                <span class="text-xs font-normal text-metronic-muted dark:text-gray-400" id="visibleFilesBadge"></span>
                            </h2>
                        </div>

                        <!-- Right Actions: Sort & View Toggle -->
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            
                            <!-- Search inside current folder -->
                            <div class="relative w-40 sm:w-52">
                                <input type="text" id="inlineSearchInput" placeholder="<?= htmlspecialchars($t['search_in_folder']) ?>"
                                    class="w-full <?= $isRtl ? 'pl-3 pr-8' : 'pr-3 pl-8' ?> py-1.5 text-xs bg-gray-50 dark:bg-[#15171C] border border-gray-200 dark:border-[#262B36] rounded-lg focus:outline-none focus:border-brand-500 focus:bg-white dark:focus:bg-[#15171C] text-metronic-heading dark:text-white placeholder-metronic-muted dark:placeholder-gray-500">
                                <svg class="w-3.5 h-3.5 text-gray-400 absolute top-2 <?= $isRtl ? 'right-2.5' : 'left-2.5' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>

                            <!-- Sort Selector -->
                            <select id="sortSelector" onchange="app.applySort(this.value)"
                                class="text-xs bg-gray-50 dark:bg-[#15171C] border border-gray-200 dark:border-[#262B36] text-metronic-heading dark:text-white rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-brand-500 cursor-pointer">
                                <option value="date_desc"><?= htmlspecialchars($t['sort_date_desc']) ?></option>
                                <option value="downloads_desc"><?= htmlspecialchars($t['sort_downloads_desc']) ?></option>
                                <option value="size_desc"><?= htmlspecialchars($t['sort_size_desc']) ?></option>
                                <option value="size_asc"><?= htmlspecialchars($t['sort_size_asc']) ?></option>
                                <option value="name_asc"><?= htmlspecialchars($t['sort_name_asc']) ?></option>
                            </select>

                            <!-- View Mode Toggle (Table / Grid) -->
                            <div class="flex items-center border border-gray-200 dark:border-[#262B36] rounded-lg p-0.5 bg-gray-50 dark:bg-[#15171C]">
                                <button id="btnViewTable" onclick="app.switchView('table')" title="<?= htmlspecialchars($t['view_table']) ?>"
                                    class="p-1 rounded text-brand-600 dark:text-brand-400 bg-white dark:bg-[#1E2129] shadow-xs transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                    </svg>
                                </button>
                                <button id="btnViewGrid" onclick="app.switchView('grid')" title="<?= htmlspecialchars($t['view_grid']) ?>"
                                    class="p-1 rounded text-gray-400 dark:text-gray-500 hover:text-metronic-heading dark:hover:text-white transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- UNIFIED LISTING: FOLDERS & FILES -->
                <div id="listingSection" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-metronic-muted dark:text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <?= htmlspecialchars($t['folder_contents']) ?>
                        </h3>
                        <div id="backButtonContainer"></div>
                    </div>

                    <!-- TABLE VIEW CONTAINER -->
                    <div id="tableViewContainer" class="bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full <?= $isRtl ? 'text-right' : 'text-left' ?> border-collapse">
                                <thead>
                                    <tr class="bg-gray-50/75 dark:bg-[#15171C]/75 border-b border-gray-100 dark:border-[#262B36] text-xs font-semibold text-metronic-muted dark:text-gray-400 uppercase tracking-wider">
                                        <th class="py-3.5 px-4"><?= htmlspecialchars($t['col_name']) ?></th>
                                        <th class="py-3.5 px-3"><?= htmlspecialchars($t['col_size']) ?></th>
                                        <th class="py-3.5 px-3"><?= htmlspecialchars($t['col_downloads']) ?></th>
                                        <th class="py-3.5 px-3"><?= htmlspecialchars($t['col_date']) ?></th>
                                        <th class="py-3.5 px-4 text-center"><?= htmlspecialchars($t['col_actions']) ?></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-[#262B36] text-sm" id="filesTableBody">
                                    <!-- Populated by JS (Folders & Files) -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- GRID VIEW CONTAINER (Switched via JS) -->
                    <div id="gridViewContainer" class="hidden grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <!-- Populated by JS (Folders & Files) -->
                    </div>
                </div>

                <!-- Empty State (When folder is empty or search returns 0) -->
                <div id="noResultsState" class="hidden bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] shadow-sm p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mx-auto flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-metronic-heading dark:text-white mb-1"><?= htmlspecialchars($t['empty_title']) ?></h3>
                    <p class="text-xs text-metronic-muted dark:text-gray-400 max-w-sm mx-auto mb-4"><?= htmlspecialchars($t['empty_desc']) ?></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notification (Copied Link) -->
    <div id="copyToast" class="fixed bottom-6 <?= $isRtl ? 'left-6' : 'right-6' ?> z-50 flex items-center gap-3 px-4 py-3 rounded-2xl bg-metronic-dark dark:bg-gray-800 text-white text-xs font-medium shadow-2xl transition-all duration-300 transform translate-y-20 opacity-0 pointer-events-none border border-transparent dark:border-gray-700">
        <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <span><?= htmlspecialchars($t['toast_copied']) ?></span>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1E2129] rounded-3xl max-w-3xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh] border border-transparent dark:border-[#262B36]">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-[#262B36] flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-metronic-heading dark:text-white" id="previewModalTitle"><?= htmlspecialchars($t['modal_preview_title']) ?></span>
                </div>
                <button type="button" onclick="app.closePreviewModal()" class="p-1 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-4 overflow-auto flex-1 flex items-center justify-center bg-gray-50 dark:bg-[#15171C] min-h-[300px]" id="previewModalContent">
                <!-- Preview content inserted here -->
            </div>
            <div class="px-6 py-3 border-t border-gray-100 dark:border-[#262B36] bg-white dark:bg-[#1E2129] flex items-center justify-end gap-2">
                <a id="previewDownloadBtn" href="#" class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition-all shadow-sm">
                    <?= htmlspecialchars($t['modal_download']) ?>
                </a>
                <button type="button" onclick="app.closePreviewModal()" class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition-all">
                    <?= htmlspecialchars($t['modal_close']) ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white dark:bg-[#1E2129] border-t border-metronic-border dark:border-[#262B36] py-4 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-metronic-muted dark:text-gray-400">
            <div class="flex items-center gap-3">
                <span><?= htmlspecialchars($t['footer_text']) ?></span>
                <span class="text-gray-300 dark:text-gray-600 select-none">&bull;</span>
            </div>
            <div class="flex items-center gap-4 dir-ltr">
                <a href="https://github.com/Mr-Alidoosti/localdlr" target="_blank" rel="noopener noreferrer"
                    title="GitHub Repository: localdlr"
                    class="flex items-center gap-2 text-metronic-muted dark:text-gray-400 hover:text-metronic-heading dark:hover:text-white transition-colors">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                    </svg>
                </a>
            </div>
        </div>
    </footer>

    <!-- Localization & Environment Data -->
    <script>
        window.APP_LANG = <?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>;
        window.CURRENT_LOCALE = '<?= $currentLang ?>';
        window.IS_RTL = <?= $isRtl ? 'true' : 'false' ?>;
    </script>
    <!-- App JavaScript -->
    <script src="assets/js/app.js"></script>
</body>
</html>
