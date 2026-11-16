<?php
/**
 * FileScanner & Helper Utilities
 * Folder-by-folder scanner, metadata extractor, and Jalali date converter.
 */

require_once __DIR__ . '/tracker.php';

function gregorian_to_jalali($gy, $gm, $gd): array {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function format_jalali_date(int $timestamp): string {
    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];

    $gy = (int) date('Y', $timestamp);
    $gm = (int) date('m', $timestamp);
    $gd = (int) date('d', $timestamp);

    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    $time = date('H:i', $timestamp);

    return sprintf('%d %s %d %s', $jd, $months[$jm], $jy, $time);
}

function format_bytes(int $bytes, int $precision = 1): string {
    $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function format_bytes_en(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function format_date(int $timestamp, string $lang = 'fa'): string {
    if ($lang === 'en') {
        return date('M j, Y H:i', $timestamp);
    }
    return format_jalali_date($timestamp);
}

function format_size(int $bytes, string $lang = 'fa', int $precision = 1): string {
    if ($lang === 'en') {
        return format_bytes_en($bytes, $precision);
    }
    return format_bytes($bytes, $precision);
}

class FileScanner {
    private string $baseDir;
    private DownloadTracker $tracker;
    private string $lang;

    public function __construct(?string $baseDir = null, ?string $lang = null) {
        $this->baseDir = $baseDir ?: dirname(__DIR__) . '/files';
        if (!is_dir($this->baseDir)) {
            @mkdir($this->baseDir, 0755, true);
        }
        $this->tracker = DownloadTracker::getInstance();
        $this->lang = $lang ?: ($_COOKIE['lang'] ?? 'fa');
        if (!in_array($this->lang, ['fa', 'en'], true)) {
            $this->lang = 'fa';
        }
    }

    public function getBaseDir(): string {
        return $this->baseDir;
    }

    /**
     * Scans a specific folder for direct subfolders and direct files.
     */
    public function scanFolder(string $relFolder = ''): array {
        $realBase = realpath($this->baseDir);
        if (!$realBase) {
            return $this->emptyFolderResult($relFolder);
        }

        $relFolder = trim(str_replace('\\', '/', $relFolder), '/');

        // Security check against path traversal
        if (strpos($relFolder, '..') !== false || strpos($relFolder, "\0") !== false) {
            $relFolder = '';
        }

        $targetPath = empty($relFolder) ? $realBase : realpath($realBase . '/' . $relFolder);

        if (!$targetPath || strpos($targetPath, $realBase) !== 0 || !is_dir($targetPath)) {
            $targetPath = $realBase;
            $relFolder = '';
        }

        $directFolders = [];
        $directFiles = [];
        $folderSize = 0;
        $allCounts = $this->tracker->getAllCounts();

        $items = @scandir($targetPath);
        if ($items === false) {
            return $this->emptyFolderResult($relFolder);
        }

        $isEn = ($this->lang === 'en');
        $rootName = $isEn ? 'Root' : 'ریشه';
        $homeName = $isEn ? 'Home' : 'خانه';
        $rootFolderName = $isEn ? 'Root Folder' : 'پوشه اصلی';

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.' || $item === 'Thumbs.db') {
                continue;
            }

            $itemFullPath = $targetPath . '/' . $item;
            $itemRelPath = empty($relFolder) ? $item : $relFolder . '/' . $item;

            if (is_dir($itemFullPath)) {
                $subInfo = $this->calcFolderStats($itemFullPath);
                $mtime = (int) filemtime($itemFullPath);
                $directFolders[] = [
                    'name' => $item,
                    'path' => $itemRelPath,
                    'folder' => empty($relFolder) ? $rootName : $relFolder,
                    'is_folder' => true,
                    'file_count' => $subInfo['file_count'],
                    'total_size' => $subInfo['total_size'],
                    'total_size_formatted' => format_size($subInfo['total_size'], $this->lang),
                    'total_size_en' => format_bytes_en($subInfo['total_size']),
                    'mtime' => $mtime,
                    'date_formatted' => format_date($mtime, $this->lang),
                    'date_iso' => date('Y-m-d H:i:s', $mtime),
                ];
            } elseif (is_file($itemFullPath)) {
                $size = (int) filesize($itemFullPath);
                $mtime = (int) filemtime($itemFullPath);
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $count = $allCounts[$itemRelPath] ?? 0;
                $folderSize += $size;

                $typeMeta = $this->resolveTypeMeta($ext, $this->lang);

                $directFiles[] = [
                    'name' => $item,
                    'path' => $itemRelPath,
                    'folder' => empty($relFolder) ? $rootName : $relFolder,
                    'size' => $size,
                    'size_formatted' => format_size($size, $this->lang),
                    'size_en' => format_bytes_en($size),
                    'extension' => $ext ?: 'file',
                    'category' => $typeMeta['category'],
                    'category_label' => $typeMeta['label'],
                    'badge_class' => $typeMeta['badge'],
                    'icon' => $typeMeta['icon'],
                    'mtime' => $mtime,
                    'date_formatted' => format_date($mtime, $this->lang),
                    'date_iso' => date('Y-m-d H:i:s', $mtime),
                    'download_count' => $count,
                    'download_url' => 'download.php?file=' . rawurlencode($itemRelPath),
                    'preview_url' => 'download.php?file=' . rawurlencode($itemRelPath) . '&preview=1',
                    'can_preview' => in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'pdf', 'mp4', 'mp3', 'txt', 'json']),
                ];
            }
        }

        // Sort folders alphabetically
        usort($directFolders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        // Sort files by mtime desc by default
        usort($directFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        // Build breadcrumbs
        $breadcrumbs = [['name' => $homeName, 'path' => '']];
        if (!empty($relFolder)) {
            $parts = explode('/', $relFolder);
            $accum = '';
            foreach ($parts as $p) {
                $accum = empty($accum) ? $p : $accum . '/' . $p;
                $breadcrumbs[] = [
                    'name' => $p,
                    'path' => $accum
                ];
            }
        }

        // Parent folder
        $parentFolder = '';
        if (!empty($relFolder) && strpos($relFolder, '/') !== false) {
            $parentFolder = substr($relFolder, 0, strrpos($relFolder, '/'));
        }

        return [
            'status' => 'success',
            'current_folder' => $relFolder,
            'current_folder_name' => empty($relFolder) ? $rootFolderName : basename($relFolder),
            'parent_folder' => $parentFolder,
            'is_root' => empty($relFolder),
            'breadcrumbs' => $breadcrumbs,
            'folders' => $directFolders,
            'files' => $directFiles,
            'stats' => [
                'folders_count' => count($directFolders),
                'files_count' => count($directFiles),
                'total_size' => $folderSize,
                'total_size_formatted' => format_size($folderSize, $this->lang),
            ]
        ];
    }

    /**
     * Calculates recursive file count and size for a folder.
     */
    private function calcFolderStats(string $dirPath): array {
        $count = 0;
        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $filename = $item->getFilename();
                if ($filename[0] === '.' || $filename === 'Thumbs.db') {
                    continue;
                }
                if ($item->isFile()) {
                    $count++;
                    $size += $item->getSize();
                }
            }
        } catch (Exception $e) {
            // handle permission errors gracefully
        }

        return ['file_count' => $count, 'total_size' => $size];
    }

    /**
     * Gets global statistics across all files and folders.
     */
    public function getGlobalStats(): array {
        $realBase = realpath($this->baseDir);
        $totalFiles = 0;
        $totalBytes = 0;
        $foldersSet = [];
        $allCounts = $this->tracker->getAllCounts();
        $totalDownloads = 0;

        if ($realBase && is_dir($realBase)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realBase, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $filename = $item->getFilename();
                if ($filename[0] === '.' || $filename === 'Thumbs.db') {
                    continue;
                }

                $realPath = $item->getRealPath();
                $relPath = ltrim(substr($realPath, strlen($realBase)), '/\\');

                if ($item->isDir()) {
                    $foldersSet[dirname($relPath) === '.' ? $relPath : dirname($relPath)] = true;
                } elseif ($item->isFile()) {
                    $totalFiles++;
                    $totalBytes += (int) $item->getSize();
                    $relKey = str_replace('\\', '/', $relPath);
                    $totalDownloads += ($allCounts[$relKey] ?? 0);
                }
            }
        }

        // Also get main root folders
        $rootFolders = [];
        if ($realBase && is_dir($realBase)) {
            $directItems = @scandir($realBase);
            if ($directItems) {
                foreach ($directItems as $it) {
                    if ($it === '.' || $it === '..' || $it[0] === '.' || $it === 'Thumbs.db') {
                        continue;
                    }
                    if (is_dir($realBase . '/' . $it)) {
                        $stat = $this->calcFolderStats($realBase . '/' . $it);
                        $rootFolders[] = [
                            'name' => $it,
                            'path' => $it,
                            'file_count' => $stat['file_count'],
                            'total_size' => $stat['total_size'],
                            'total_size_formatted' => format_size($stat['total_size'], $this->lang),
                        ];
                    }
                }
            }
        }

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalBytes,
            'total_size_formatted' => format_size($totalBytes, $this->lang),
            'total_size_en' => format_bytes_en($totalBytes),
            'total_downloads' => $this->tracker->getTotalDownloads(),
            'total_folders' => count($rootFolders),
            'root_folders' => $rootFolders
        ];
    }

    private function emptyFolderResult(string $relFolder): array {
        $isEn = ($this->lang === 'en');
        return [
            'status' => 'error',
            'current_folder' => $relFolder,
            'current_folder_name' => $isEn ? 'Not Found' : 'یافت نشد',
            'parent_folder' => '',
            'is_root' => true,
            'breadcrumbs' => [['name' => $isEn ? 'Home' : 'خانه', 'path' => '']],
            'folders' => [],
            'files' => [],
            'stats' => [
                'folders_count' => 0,
                'files_count' => 0,
                'total_size' => 0,
                'total_size_formatted' => $isEn ? '0 B' : '۰ بایت'
            ]
        ];
    }

    public function resolveTypeMeta(string $ext, string $lang = 'fa'): array {
        $isEn = ($lang === 'en');
        switch ($ext) {
            case 'pdf':
                return [
                    'category' => 'document',
                    'label' => $isEn ? 'PDF Document' : 'سند PDF',
                    'badge' => 'bg-red-50 text-red-600 border-red-200',
                    'icon' => 'pdf'
                ];
            case 'doc':
            case 'docx':
            case 'rtf':
            case 'odt':
                return [
                    'category' => 'document',
                    'label' => $isEn ? 'Word Document' : 'سند Word',
                    'badge' => 'bg-blue-50 text-blue-600 border-blue-200',
                    'icon' => 'word'
                ];
            case 'xls':
            case 'xlsx':
            case 'csv':
                return [
                    'category' => 'document',
                    'label' => $isEn ? 'Excel Spreadsheet' : 'اکسل / جدول',
                    'badge' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                    'icon' => 'excel'
                ];
            case 'ppt':
            case 'pptx':
                return [
                    'category' => 'document',
                    'label' => $isEn ? 'Presentation' : 'پرزنتیشن',
                    'badge' => 'bg-orange-50 text-orange-600 border-orange-200',
                    'icon' => 'powerpoint'
                ];
            case 'zip':
            case 'rar':
            case '7z':
            case 'tar':
            case 'gz':
            case 'bz2':
                return [
                    'category' => 'archive',
                    'label' => $isEn ? 'Compressed Archive' : 'فایل فشرده',
                    'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'icon' => 'archive'
                ];
            case 'exe':
            case 'msi':
            case 'pkg':
            case 'dmg':
            case 'deb':
            case 'rpm':
            case 'apk':
                return [
                    'category' => 'software',
                    'label' => $isEn ? 'Software / Installer' : 'نرم‌افزار / نصب‌کننده',
                    'badge' => 'bg-purple-50 text-purple-600 border-purple-200',
                    'icon' => 'software'
                ];
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'svg':
            case 'webp':
            case 'ico':
                return [
                    'category' => 'image',
                    'label' => $isEn ? 'Image' : 'تصویر',
                    'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    'icon' => 'image'
                ];
            case 'mp4':
            case 'mkv':
            case 'avi':
            case 'mov':
            case 'webm':
                return [
                    'category' => 'video',
                    'label' => $isEn ? 'Video' : 'ویدیو',
                    'badge' => 'bg-rose-50 text-rose-600 border-rose-200',
                    'icon' => 'video'
                ];
            case 'mp3':
            case 'wav':
            case 'ogg':
            case 'flac':
                return [
                    'category' => 'audio',
                    'label' => $isEn ? 'Audio File' : 'فایل صوتی',
                    'badge' => 'bg-pink-50 text-pink-600 border-pink-200',
                    'icon' => 'audio'
                ];
            case 'json':
            case 'js':
            case 'php':
            case 'html':
            case 'css':
            case 'sql':
            case 'py':
            case 'sh':
            case 'xml':
                return [
                    'category' => 'code',
                    'label' => $isEn ? 'Code / Script' : 'کد / اسکریپت',
                    'badge' => 'bg-slate-100 text-slate-700 border-slate-300',
                    'icon' => 'code'
                ];
            default:
                return [
                    'category' => 'other',
                    'label' => $isEn ? (strtoupper($ext) . ' File') : ('فایل ' . strtoupper($ext)),
                    'badge' => 'bg-gray-100 text-gray-700 border-gray-200',
                    'icon' => 'file'
                ];
        }
    }
}
