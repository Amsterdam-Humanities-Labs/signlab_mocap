<?php
header('Content-Type: application/json');

include('../mysql_config.php');

// Disable PHP warnings and set custom error and exception handlers
error_reporting(E_ERROR | E_PARSE);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(array("success" => false, "error" => $e->getMessage()));
    exit();
});

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    throw new Exception("Database connection failed: " . $conn->connect_error);
}

// Query to get the last 50 records from mocap_files
$query = "
    SELECT 
        id AS file_id,
        glos AS file_glos,
        take AS file_take,
        datetime AS file_datetime,
        avatarName AS file_avatarName,
        ll_metadata,
        filename,
        vicon_fbx,
        vicon_csv
    FROM 
        mocap_files
    ORDER BY 
        id DESC
";

// Execute the query
$result = $conn->query($query);
if (!$result) {
    throw new Exception("Query failed: " . $conn->error);
}

// Process the data
$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Extract fields
        $file_id = $row['file_id'];
        $file_glos = $row['file_glos'];
        $file_take = $row['file_take'];
        $file_datetime = $row['file_datetime'];
        $file_avatarName = $row['file_avatarName'];
        $filename = $row['filename'];
        $ll_metadata = $row['ll_metadata'];
        $vicon_fbx = $row['vicon_fbx'];
        $vicon_csv = $row['vicon_csv'];

        // Replace .fbx with .glb in filename if filename exists
        if (!empty($filename)) {
            $filename = str_replace(".fbx", ".glb", $filename);
        }

        // Construct the video URL
        $video_url = "https://signcollect.nl/uploads/" . $file_glos . ".mp4";

        // Prepare the record
        $record = [
            "file_id" => $file_id,
            "glos" => $file_glos,
            "take" => $file_take,
            "datetime" => $file_datetime,
            "avatarName" => $file_avatarName,
            "video_url" => $video_url,
            "filename" => $filename,
            "ll_metadata" => $ll_metadata,
            'vicon_fbx' => $vicon_fbx,
            'vicon_csv' => $vicon_csv,
        ];

        $data[] = $record;
    }
}

// Close the database connection
$conn->close();

// Return the data as JSON
$response = [
    "success" => true,
    "count" => count($data),
    "data" => $data
];

echo json_encode($response);
?>
