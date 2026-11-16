<?php
/**
 * Download Handler
 * Securely serves files from the `files/` directory, updates download metrics,
 * and supports preview and AJAX stats.
 */

require_once __DIR__ . '/includes/tracker.php';
require_once __DIR__ . '/includes/scanner.php';

$tracker = DownloadTracker::getInstance();
$baseDir = realpath(__DIR__ . '/files');

if (!$baseDir) {
    http_response_code(500);
    die('Directory "files/" does not exist.');
}

$requestedFile = $_GET['file'] ?? '';
$action = $_GET['action'] ?? 'download';
$preview = isset($_GET['preview']) && $_GET['preview'] == '1';

if (empty($requestedFile)) {
    http_response_code(400);
    die('پارامتر فایل مشخص نشده است.');
}

// Clean and normalize path
$requestedFile = str_replace('\\', '/', $requestedFile);
$requestedFile = ltrim($requestedFile, '/');

// Security check against directory traversal
if (strpos($requestedFile, '..') !== false || strpos($requestedFile, "\0") !== false) {
    http_response_code(403);
    die('دسترسی غیرمجاز (مسیر نامعتبر).');
}

$targetPath = realpath($baseDir . '/' . $requestedFile);

// Ensure the target is inside $baseDir and is a file
if ($targetPath === false || strpos($targetPath, $baseDir) !== 0 || !is_file($targetPath)) {
    http_response_code(404);
    die('فایل مورد نظر یافت نشد.');
}

$basename = basename($targetPath);

// Prevent access to hidden files
if ($basename[0] === '.' || $basename === 'Thumbs.db') {
    http_response_code(403);
    die('دسترسی به این فایل امکان‌پذیر نیست.');
}

$relPath = ltrim(substr($targetPath, strlen($baseDir)), '/\\');
$relPath = str_replace('\\', '/', $relPath);

// Handle AJAX query for count only
if ($action === 'count') {
    $newCount = $tracker->increment($relPath);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'file' => $relPath,
        'download_count' => $newCount,
        'total_downloads' => $tracker->getTotalDownloads()
    ]);
    exit;
}

// Increment download count only for real downloads (not inline previews or resume chunks)
$isSubsequentRange = isset($_SERVER['HTTP_RANGE']) && !preg_match('/bytes=\s*0-/', $_SERVER['HTTP_RANGE']);
if (!$preview && !$isSubsequentRange) {
    $tracker->increment($relPath);
}

// Determine MIME type
$ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    '7z' => 'application/x-7z-compressed',
    'tar' => 'application/x-tar',
    'gz' => 'application/gzip',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'json' => 'application/json',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'exe' => 'application/octet-stream',
    'dmg' => 'application/x-apple-diskimage',
    'pkg' => 'application/octet-stream',
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';
if (function_exists('mime_content_type') && !isset($mimeTypes[$ext])) {
    $detectedMime = @mime_content_type($targetPath);
    if ($detectedMime) {
        $mime = $detectedMime;
    }
}

// Clean any previous output buffer
while (ob_get_level()) {
    ob_end_clean();
}

$fileSize = filesize($targetPath);
$disposition = $preview ? 'inline' : 'attachment';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($basename) . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $fileSize);
header('Accept-Ranges: bytes');

// Chunked output streaming for stability on large files
$handle = fopen($targetPath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 1024 * 64);
        flush();
    }
    fclose($handle);
} else {
    readfile($targetPath);
}
exit;

