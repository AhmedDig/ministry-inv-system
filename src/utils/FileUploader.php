<?php
namespace Ministry\Utils;

class FileUploader
{
    private const MAX_FILES = 3;
    private const MAX_SIZE = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    private const STORAGE_BASE = __DIR__ . '/../../storage/tickets';

    /**
     * Normalize $_FILES['attachments'] into a flat array of individual file entries.
     */
    public static function normalizeFilesArray(?array $raw): array
    {
        if (!$raw || empty($raw['name']) || !is_array($raw['name'])) {
            return [];
        }
        $files = [];
        foreach ($raw['name'] as $i => $name) {
            if ($raw['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'tmp_name' => $raw['tmp_name'][$i],
                'size' => (int) $raw['size'][$i],
                'error' => (int) $raw['error'][$i],
            ];
        }
        return $files;
    }

    /**
     * Validate a list of files. Returns array of errors (empty = all good).
     */
    public static function validate(array $files): array
    {
        $errors = [];

        if (count($files) > self::MAX_FILES) {
            $errors[] = 'يمكن رفع ' . self::MAX_FILES . ' ملفات كحد أقصى.';
            return $errors;
        }

        foreach ($files as $i => $f) {
            $n = $i + 1;
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "الملف رقم $n: حدث خطأ أثناء الرفع.";
                continue;
            }
            if ($f['size'] > self::MAX_SIZE) {
                $errors[] = "الملف رقم $n: الحجم يتجاوز 2 ميجابايت.";
                continue;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            if (!isset(self::ALLOWED[$mime])) {
                $errors[] = "الملف رقم $n: النوع غير مسموح (JPG / PNG / PDF فقط).";
            }
        }
        return $errors;
    }

    /**
     * Move all files for a ticket into its storage folder.
     * Returns array of attachment records ready for DB insert.
     * Throws \RuntimeException on failure (caller must clean up).
     */
    public static function moveForTicket(int $ticketId, array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $dir = self::STORAGE_BASE . '/' . $ticketId;
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new \RuntimeException('Failed to create storage directory');
        }

        $moved = [];
        $records = [];

        try {
            foreach ($files as $f) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                $ext = self::ALLOWED[$mime];
                $random = bin2hex(random_bytes(12));
                $filename = $random . '.' . $ext;
                $dest = $dir . '/' . $filename;

                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new \RuntimeException('Failed to move uploaded file');
                }
                $moved[] = $dest;

                $records[] = [
                    'file_path' => $ticketId . '/' . $filename, // relative
                    'original_name' => mb_substr($f['name'], 0, 255),
                    'mime_type' => $mime,
                    'file_size' => $f['size'],
                ];
            }
        } catch (\Throwable $e) {
            // Clean up partially moved files
            foreach ($moved as $p) {
                @unlink($p);
            }
            @rmdir($dir);
            throw $e;
        }

        return $records;
    }
}