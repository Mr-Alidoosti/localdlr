<?php
/**
 * DownloadTracker
 * Manages file download counts using SQLite with JSON fallback.
 */

class DownloadTracker {
    private static ?DownloadTracker $instance = null;
    private ?PDO $pdo = null;
    private string $jsonPath;
    private bool $useSqlite = false;

    private function __construct() {
        $dataDir = dirname(__DIR__) . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        $sqlitePath = $dataDir . '/downloads.sqlite';
        $this->jsonPath = $dataDir . '/downloads.json';

        if (extension_loaded('pdo_sqlite')) {
            try {
                $this->pdo = new PDO('sqlite:' . $sqlitePath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS downloads (
                        file_path TEXT PRIMARY KEY,
                        count INTEGER NOT NULL DEFAULT 0,
                        last_downloaded_at TEXT
                    )
                ");
                $this->useSqlite = true;
            } catch (Exception $e) {
                $this->useSqlite = false;
            }
        }

        if (!$this->useSqlite && !file_exists($this->jsonPath)) {
            @file_put_contents($this->jsonPath, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public static function getInstance(): DownloadTracker {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function increment(string $relativePath): int {
        $relativePath = ltrim($relativePath, '/\\');
        $now = date('Y-m-d H:i:s');

        if ($this->useSqlite && $this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO downloads (file_path, count, last_downloaded_at)
                    VALUES (:path, 1, :now)
                    ON CONFLICT(file_path) DO UPDATE SET
                        count = count + 1,
                        last_downloaded_at = :now
                ");
                $stmt->execute([':path' => $relativePath, ':now' => $now]);

                $stmt = $this->pdo->prepare("SELECT count FROM downloads WHERE file_path = :path");
                $stmt->execute([':path' => $relativePath]);
                return (int) $stmt->fetchColumn();
            } catch (Exception $e) {
                // fallback to JSON if sqlite fails
            }
        }

        // JSON Fallback
        return $this->incrementJson($relativePath, $now);
    }

    public function getCount(string $relativePath): int {
        $relativePath = ltrim($relativePath, '/\\');

        if ($this->useSqlite && $this->pdo) {
            try {
                $stmt = $this->pdo->prepare("SELECT count FROM downloads WHERE file_path = :path");
                $stmt->execute([':path' => $relativePath]);
                $val = $stmt->fetchColumn();
                return $val !== false ? (int) $val : 0;
            } catch (Exception $e) {
                // fallback
            }
        }

        $data = $this->readJson();
        return isset($data[$relativePath]['count']) ? (int) $data[$relativePath]['count'] : 0;
    }

    public function getAllCounts(): array {
        $result = [];

        if ($this->useSqlite && $this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT file_path, count FROM downloads");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $result[$row['file_path']] = (int) $row['count'];
                }
                return $result;
            } catch (Exception $e) {
                // fallback
            }
        }

        $data = $this->readJson();
        foreach ($data as $path => $info) {
            $result[$path] = (int) ($info['count'] ?? 0);
        }
        return $result;
    }

    public function getTotalDownloads(): int {
        if ($this->useSqlite && $this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT SUM(count) FROM downloads");
                $sum = $stmt->fetchColumn();
                return $sum ? (int) $sum : 0;
            } catch (Exception $e) {
                // fallback
            }
        }

        $data = $this->readJson();
        $total = 0;
        foreach ($data as $info) {
            $total += (int) ($info['count'] ?? 0);
        }
        return $total;
    }

    private function readJson(): array {
        if (!file_exists($this->jsonPath)) {
            return [];
        }
        $content = @file_get_contents($this->jsonPath);
        if (!$content) {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function incrementJson(string $path, string $now): int {
        $fp = @fopen($this->jsonPath, 'c+');
        if (!$fp) {
            return 1;
        }

        $newCount = 1;
        if (flock($fp, LOCK_EX)) {
            $size = filesize($this->jsonPath);
            $content = $size > 0 ? fread($fp, $size) : '';
            $data = json_decode($content, true);
            if (!is_array($data)) {
                $data = [];
            }

            if (isset($data[$path]['count'])) {
                $data[$path]['count']++;
                $data[$path]['last_downloaded_at'] = $now;
            } else {
                $data[$path] = [
                    'count' => 1,
                    'last_downloaded_at' => $now
                ];
            }
            $newCount = $data[$path]['count'];

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return $newCount;
    }
}

