<?php
namespace Rongie\QuickHire\Services;

use Exception;

class FileUpload
{
    private int $maxBytes = 5_000_000; // 5MB
    private array $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private array $allowedResumeTypes = ['application/pdf'];

    /** Upload avatar image, return saved relative path like "uploads/avatars/xxx.png" */
    public function uploadAvatar(array $file, string $targetDirAbs, string $targetDirRel): ?string
    {
        if (empty($file['name'])) return null;
        $this->validateUpload($file);

        if (!in_array($file['type'] ?? '', $this->allowedImageTypes, true)) {
            throw new Exception("Avatar must be JPG, PNG, or WEBP.");
        }

        $ext = $this->safeExt($file['name']);
        $name = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;

        $this->ensureDir($targetDirAbs);
        $destAbs = rtrim($targetDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
            throw new Exception("Failed to upload avatar.");
        }

        return rtrim($targetDirRel, '/\\') . '/' . $name;
    }

    /** Upload resume PDF, return saved relative path like "uploads/resumes/xxx.pdf" */
    public function uploadResume(array $file, string $targetDirAbs, string $targetDirRel): ?string
    {
        if (empty($file['name'])) return null;
        $this->validateUpload($file);

        if (!in_array($file['type'] ?? '', $this->allowedResumeTypes, true)) {
            throw new Exception("Resume must be a PDF.");
        }

        $name = 'resume_' . bin2hex(random_bytes(8)) . '.pdf';

        $this->ensureDir($targetDirAbs);
        $destAbs = rtrim($targetDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
            throw new Exception("Failed to upload resume.");
        }

        return rtrim($targetDirRel, '/\\') . '/' . $name;
    }

    // ---------- private helpers (encapsulation) ----------
    private function validateUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error.");
        }
        if (($file['size'] ?? 0) > $this->maxBytes) {
            throw new Exception("File too large (max 5MB).");
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception("Cannot create upload directory.");
            }
        }
    }

    private function safeExt(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext ?: 'bin';
    }
}