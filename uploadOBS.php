<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDirectory = '/web/gebarenoverleg_media/mocapVideos/';

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
