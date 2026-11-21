<?php
/**
 * CampusFix - Secure Multi-Image Upload Service
 * Enforces MIME inspection, size limits, extension whitelisting, and sanitized naming.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

class UploadService
{
    private static array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    private static array $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
    private static int $maxSizeBytes   = 5242880; // 5 MB

    /**
     * Process multiple file uploads from $_FILES
     *
     * @param array $fileInput e.g. $_FILES['photos']
     * @param string $destinationDir Absolute storage directory
     * @param string $publicUrlPrefix Relative or public URL prefix
     * @param int $maxFiles Max allowed images per submission
     * @return array List of uploaded file descriptors or throws Exception
     * @throws Exception
     */
    public static function handleUploads(
        array $fileInput,
        string $destinationDir = TICKET_UPLOADS_PATH,
        string $publicUrlPrefix = 'uploads/tickets/',
        int $maxFiles = 3
    ): array {
        if (!isset($fileInput['name']) || empty($fileInput['name'])) {
            return [];
        }

        // Normalize $_FILES structure if multiple files uploaded
        $files = self::reArrayFiles($fileInput);
        if (count($files) > $maxFiles) {
            throw new Exception("You can upload a maximum of {$maxFiles} images per report.");
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $uploaded = [];

        foreach ($files as $file) {
            // Skip empty inputs
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("An error occurred while uploading file '{$file['name']}' (Code {$file['error']}).");
            }

            // 1. File Size Verification
            if ($file['size'] > self::$maxSizeBytes) {
                throw new Exception("File '{$file['name']}' exceeds the maximum allowed size of 5MB.");
            }

            // 2. Extension Verification
            $origName = basename($file['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, self::$allowedExts, true)) {
                throw new Exception("File '{$origName}' has an invalid extension. Only JPG, PNG, and WEBP formats are permitted.");
            }

            // 3. Genuine MIME Type Sniffing via Fileinfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, self::$allowedMimes, true)) {
                throw new Exception("File '{$origName}' does not contain valid image data (detected: {$mimeType}).");
            }

            // 4. Generate Cryptographically Random File Name
            $safeFileName = 'cf_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;

            // 5. Store File Securely
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("Failed to save uploaded file '{$origName}'. Please check folder write permissions.");
            }

            $uploaded[] = [
                'file_name' => $safeFileName,
                'file_path' => $publicUrlPrefix . $safeFileName,
                'file_size' => (int) $file['size'],
                'file_type' => $mimeType,
                'orig_name' => $origName
            ];
        }

        return $uploaded;
    }

    /**
     * Persist uploaded image records to ticket_images table
     *
     * @param PDO $pdo
     * @param int $ticketId
     * @param int $userId
     * @param array $uploadedFiles Array returned from handleUploads()
     * @param string $imageType 'fault' or 'resolution'
     * @return void
     */
    public static function saveTicketImages(PDO $pdo, int $ticketId, int $userId, array $uploadedFiles, string $imageType = 'fault'): void
    {
        if (empty($uploadedFiles)) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ticket_images 
            (ticket_id, file_path, file_name, file_size, file_type, uploaded_by, image_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($uploadedFiles as $img) {
            $stmt->execute([
                $ticketId,
                $img['file_path'],
                $img['file_name'],
                $img['file_size'],
                $img['file_type'],
                $userId,
                $imageType
            ]);
        }
    }

    /**
     * Normalize multi-file $_FILES array into array of associative file arrays
     *
     * @param array $filePost
     * @return array
     */
    private static function reArrayFiles(array $filePost): array
    {
        if (!is_array($filePost['name'])) {
            return [$filePost];
        }

        $fileAry = [];
        $fileCount = count($filePost['name']);
        $fileKeys = array_keys($filePost);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $fileAry[$i][$key] = $filePost[$key][$i];
            }
        }

        return $fileAry;
    }
}

