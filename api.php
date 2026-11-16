<?php
/**
 * AJAX API Endpoint
 * Serves folder contents, file metadata, and metrics.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/tracker.php';
require_once __DIR__ . '/includes/scanner.php';

$action = $_GET['action'] ?? 'folder';
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'fa';
if (!in_array($lang, ['fa', 'en'], true)) {
    $lang = 'fa';
}
$scanner = new FileScanner(null, $lang);
$tracker = DownloadTracker::getInstance();

switch ($action) {
    case 'folder':
        $folder = $_GET['folder'] ?? '';
        $data = $scanner->scanFolder($folder);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'stats':
        $stats = $scanner->getGlobalStats();
        echo json_encode([
            'status' => 'success',
            'stats' => $stats
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'count':
        $file = $_GET['file'] ?? '';
        if (empty($file)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'پارامتر فایل الزامی است']);
            exit;
        }
        $count = $tracker->increment($file);
        echo json_encode([
            'status' => 'success',
            'file' => $file,
            'download_count' => $count,
            'total_downloads' => $tracker->getTotalDownloads()
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر است']);
        break;
}
exit;

