<?php

declare(strict_types=1);

namespace App\Helpers;

class FileUpload
{
    private array $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private int $maxSize; // in bytes
    private string $uploadPath;

    public function __construct(?string $uploadPath = null, ?int $maxSize = null)
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->uploadPath = $uploadPath ?? $config['upload']['path'] . '/patient_files';
        $this->maxSize = $maxSize ?? $config['upload']['max_size'];
    }

    /**
     * Upload a file and return its stored info.
     */
    public function upload(array $file, ?string $subdir = null): array
    {
        $this->validateFile($file);

        $targetDir = $this->uploadPath;
        if ($subdir) {
            $targetDir .= '/' . $subdir;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $storedFilename = $this->generateFilename($extension);
        $targetPath = $targetDir . '/' . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return [
            'original_filename' => basename($file['name']),
            'stored_filename' => ($subdir ? $subdir . '/' : '') . $storedFilename,
            'mime_type' => $file['type'],
            'file_size' => $file['size'],
        ];
    }

    /**
     * Delete a stored file.
     */
    public function delete(string $storedFilename): bool
    {
        $filePath = $this->uploadPath . '/' . $storedFilename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Get the full path of a stored file.
     */
    public function getPath(string $storedFilename): string
    {
        return $this->uploadPath . '/' . $storedFilename;
    }

    /**
     * Validate an uploaded file.
     */
    private function validateFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error: ' . $this->getUploadErrorMessage($file['error']));
        }

        if ($file['size'] > $this->maxSize) {
            throw new \RuntimeException('File size exceeds the maximum allowed size of ' . $this->formatBytes($this->maxSize) . '.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \RuntimeException('File type not allowed. Allowed: ' . implode(', ', $this->allowedExtensions));
        }

        // Verify MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new \RuntimeException('File MIME type not allowed: ' . $mimeType);
        }

        // Check for PHP content in the file
        $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if (preg_match('/<\?php|<\?=|<script/i', $content)) {
            throw new \RuntimeException('File contains potentially malicious content.');
        }
    }

    /**
     * Generate a unique filename.
     */
    private function generateFilename(string $extension): string
    {
        return date('Y/m/') . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error',
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
