<?php
/**
 * Upload Helper
 * Central, reusable image upload utility for all admin panel upload forms.
 * ─────────────────────────────────────────────────────────────────────────
 * Usage:
 *   [$path, $error] = upload_image($_FILES['photo'], 'profiles');
 *   if ($error) { // show error
 *   } else { // store $path in DB: 'uploads/profiles/filename.jpg'
 *   }
 */

/**
 * Handles a single image upload with full validation.
 *
 * @param  array  $file       Entry from $_FILES (e.g. $_FILES['photo'])
 * @param  string $subfolder  Destination subfolder inside /uploads/ (e.g. 'profiles')
 * @param  string $prefix     Filename prefix (e.g. 'trainer', 'member')
 * @param  int    $maxBytes   Max allowed size in bytes (default 10 MB)
 * @return array{0:string,1:string}  [relative_path_on_success, error_message_on_failure]
 *                                   Exactly one of the two will be non-empty.
 */
function upload_image(
    array  $file,
    string $subfolder,
    string $prefix   = 'img',
    int    $maxBytes = 10 * 1024 * 1024   // 10 MB default
): array {
    // ── 1. PHP upload error ───────────────────────────────────────────────
    $uploadErr = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadErr === UPLOAD_ERR_NO_FILE) {
        return ['', ''];               // nothing selected — not an error
    }
    if ($uploadErr !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary upload folder found on server.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a server extension.',
        ];
        return ['', $phpErrors[$uploadErr] ?? 'Upload failed (error code ' . $uploadErr . ').'];
    }

    // ── 2. File type validation (extension + MIME) ────────────────────────
    $ext     = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',  'webp'  => 'image/webp'];

    if (!array_key_exists($ext, $allowed)) {
        return ['', 'Only JPG, JPEG, PNG, and WebP images are allowed.'];
    }

    // Verify real MIME type via finfo (not user-supplied Content-Type)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    if (!in_array($realMime, array_values($allowed), true)) {
        return ['', 'Invalid image file. Please upload a real JPG, PNG, or WebP image.'];
    }

    // ── 3. File size ──────────────────────────────────────────────────────
    if ((int)$file['size'] > $maxBytes) {
        $limitMb = round($maxBytes / 1024 / 1024, 0);
        return ['', "Image must be smaller than {$limitMb} MB."];
    }

    // ── 4. Ensure upload directory exists and is writable ─────────────────
    // Support both: files inside /admin/xxx/ (need ../../uploads/) and root files (need ../uploads/)
    // We resolve from UPLOAD_PATH constant defined in config.php.
    if (!defined('UPLOAD_PATH')) {
        return ['', 'Server configuration error: UPLOAD_PATH is not defined.'];
    }
    $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . trim($subfolder, '/') . '/';

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return ['', 'Upload folder could not be created. Please contact your administrator.'];
        }
    }

    if (!is_writable($uploadDir)) {
        return ['', 'Upload folder is not writable. Please contact your administrator.'];
    }

    // ── 5. Generate unique filename ───────────────────────────────────────
    $safePrefix  = preg_replace('/[^a-z0-9_\-]/', '', strtolower($prefix));
    $filename    = $safePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    // ── 6. Move file ──────────────────────────────────────────────────────
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['', 'Could not save the uploaded file. Check folder permissions.'];
    }

    // Return relative path suitable for DB storage and SITE_URL prefixing
    return ['uploads/' . trim($subfolder, '/') . '/' . $filename, ''];
}

/**
 * Deletes an old upload file if it is a local relative path (not an external URL).
 *
 * @param string $relativePath  e.g. 'uploads/profiles/trainer_xyz.jpg'
 */
function upload_delete_old(string $relativePath): void
{
    if ($relativePath === '' || str_starts_with($relativePath, 'http')) {
        return; // skip external URLs
    }
    if (!defined('UPLOAD_PATH')) return;
    // UPLOAD_PATH ends with /uploads/
    $abs = rtrim(dirname(rtrim(UPLOAD_PATH, '/')), '/') . '/' . ltrim($relativePath, '/');
    if (is_file($abs)) {
        @unlink($abs);
    }
}
