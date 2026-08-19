<?php
header('Content-Type: application/json; charset=utf-8');

include('../mysql_config.php');

// ...existing error handling and configuration...
error_reporting(E_ERROR | E_PARSE);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
    exit();
});

// Create database connection
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    throw new Exception("Database connection failed: " . $conn->connect_error);
}

// Override any provided limit to force 100 records per page
$limit = 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = ($page > 0) ? $page : 1;
$offset = ($page - 1) * $limit;

// Main query to fetch records including glos, video, and take_file
$query = "SELECT glos, video, take_file FROM csl_glosses WHERE take is NOT NULL LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ii", $limit, $offset);
if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    // Ensure each field is valid UTF-8; convert if necessary.
    foreach ($row as $key => $value) {
        if ($value !== null) {
            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
    }
    $data[] = $row;
}
$stmt->close();

// Handle counts and pagination if page is 1
if ($page === 1) {
    $totalQuery = "SELECT COUNT(*) AS total FROM csl_glosses";
    $totalStmt = $conn->prepare($totalQuery);
    if (!$totalStmt) {
        throw new Exception("Prepare total count failed: " . $conn->error);
    }
    if (!$totalStmt->execute()) {
        throw new Exception("Execute total count failed: " . $totalStmt->error);
    }
    $totalResult = $totalStmt->get_result();
    $total = 0;
    if ($totalResult->num_rows > 0) {
        $row = $totalResult->fetch_assoc();
        $total = (int)$row['total'];
    }
    $totalStmt->close();
    $pagination = [
        "current_page" => $page,
        "limit" => $limit,
        "total_records" => $total,
        "total_pages" => ceil($total / $limit)
    ];
    // For counts, mimic structure from getRecords.php
    $counts = [
        "unique_glos_count" => $total,
        "count_all" => $total,
        "count_ll_metadata" => 0,
        "count_vicon_fbx" => 0,
        "count_vicon_csv" => 0
    ];
} else {
    $pagination = [
        "current_page" => $page,
        "limit" => $limit,
        "total_records" => null,
        "total_pages" => null
    ];
    $counts = [
        "unique_glos_count" => 0,
        "count_all" => 0,
        "count_ll_metadata" => 0,
        "count_vicon_fbx" => 0,
        "count_vicon_csv" => 0
    ];
}

// Prepare the JSON response
$response = [
    "success" => true,
    "data" => $data,
    "pagination" => $pagination,
    "counts" => $counts
];
// print_r($response);
$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
