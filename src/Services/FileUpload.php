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

    /** Save a camera-captured avatar data URL, return saved relative path. */
    public function saveCapturedAvatar(?string $dataUrl, string $targetDirAbs, string $targetDirRel): ?string
    {
        $dataUrl = trim((string)$dataUrl);
        if ($dataUrl === '') {
            return null;
        }

        // If it doesn't look like a valid data URL at all, treat it as "no new photo"
        // rather than throwing — this handles browser form-replay on hard refresh
        // where the field value may be stale, truncated, or otherwise invalid.
        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            return null;
        }

        $imageType = strtolower($matches[1]) === 'jpg' ? 'jpeg' : strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) === 0) {
            return null; // Corrupt data — fall back to existing avatar
        }
        if (strlen($binary) > $this->maxBytes) {
            throw new Exception("Captured avatar is too large (max 5MB).");
        }

        $info = @getimagesizefromstring($binary);
        if (!$info || empty($info['mime']) || !in_array($info['mime'], $this->allowedImageTypes, true)) {
            return null; // Not a valid image — fall back to existing avatar
        }

        $ext = $imageType === 'jpeg' ? 'jpg' : $imageType;
        $name = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;

        $this->ensureDir($targetDirAbs);
        $destAbs = rtrim($targetDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($destAbs, $binary) === false) {
            throw new Exception("Failed to save captured avatar.");
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
