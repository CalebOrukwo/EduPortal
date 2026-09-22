<?php
/**
 * File Upload Processor
 * File: config/upload.php
 */

/**
 * Handles proof of payment file upload
 *
 * @param array  $file              The $_FILES['proof_of_payment'] array item.
 * @param string $uploadDirectory Relative path to target upload folder.
 * @param int    $maxSizeBytes     Maximum allowed file size in bytes (Default: 2MB).
 * @return array                    Result status, message, and target file path.
 */
function processProofUpload(array $file, string $uploadDirectory = '../uploads/payments/', int $maxSizeBytes = 2097152): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Invalid file upload parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['status' => false, 'message' => 'No payment proof file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['status' => false, 'message' => 'File size exceeds server upload limits.'];
        default:
            return ['status' => false, 'message' => 'An unknown error occurred during upload.'];
    }

    if ($file['size'] > $maxSizeBytes) {
        return ['status' => false, 'message' => 'File size exceeds maximum allowed limit (2MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf'
    ];

    if (!array_key_exists($mimeType, $allowedMimeTypes)) {
        return ['status' => false, 'message' => 'Invalid file format. Only JPG, PNG, and PDF files are allowed.'];
    }

    $extension = $allowedMimeTypes[$mimeType];

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true)) {
            return ['status' => false, 'message' => 'Failed to create upload destination folder.'];
        }
    }

    $fileName = sprintf('proof_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(8)), $extension);
    $targetPath = rtrim($uploadDirectory, '/') . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => false, 'message' => 'Failed to move uploaded file to destination directory.'];
    }

    return [
        'status'    => true,
        'message'   => 'File uploaded successfully.',
        'file_name' => $fileName,
        'file_path' => $targetPath
    ];
}
?>