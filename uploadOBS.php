<?php

// signcollect-lib's install-root resolver: sc_path(), sc_dir(), sc_root().
// Vendored shim - it finds /web/lib/paths.php, or falls back to /web.
require_once __DIR__ . '/sc_paths.php';

// The OBS capture machine authenticates with a shared token in the
// X-Api-Token header: SC_UPLOAD_TOKEN from the signcollect-lib env file
// (sc_env()), or from the process environment on a host without the
// library. Unset means every upload is refused - this writes into the
// media tree.
function uploadobs_token_ok() {
    $want = '';
    if (function_exists('sc_env')) {
        try { $want = (string)(sc_env()['SC_UPLOAD_TOKEN'] ?? ''); } catch (RuntimeException $e) {}
    }
    if ($want === '') $want = (string)getenv('SC_UPLOAD_TOKEN');
    if ($want === '') {
        error_log('uploadOBS.php: SC_UPLOAD_TOKEN is not configured - refusing upload');
        return false;
    }
    return hash_equals($want, (string)($_SERVER['HTTP_X_API_TOKEN'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !uploadobs_token_ok()) {
    http_response_code(401);
    echo "Missing or invalid X-Api-Token.\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDirectory = sc_dir('media', 'mocapVideos');

    // Handle video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $videoTmpPath = $_FILES['video']['tmp_name'];
        $videoName = basename($_FILES['video']['name']);
        //get extension from videoName and lowercase it
        $ext = strtolower(pathinfo($videoName, PATHINFO_EXTENSION));
        //get basename from videoName
        $videoName = pathinfo($videoName, PATHINFO_FILENAME);
        //add extension to videoName
        $videoName = $videoName . "." . $ext;



        if (move_uploaded_file($videoTmpPath, $uploadDirectory . $videoName)) {
            echo "Video uploaded successfully.\n";
        } else {
            echo "Failed to upload video.\n";
        }
    } else {
        echo "No video file uploaded or there was an upload error.\n";
    }

    // Handle thumbnail upload
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbTmpPath = $_FILES['thumbnail']['tmp_name'];
        $thumbName = basename($_FILES['thumbnail']['name']);

        if (move_uploaded_file($thumbTmpPath, $uploadDirectory . $thumbName)) {
            echo "Thumbnail uploaded successfully.\n";
        } else {
            echo "Failed to upload thumbnail.\n";
        }
    } else {
        echo "No thumbnail file uploaded or there was an upload error.\n";
    }
} else {
    echo "Invalid request method.";
}
?>
