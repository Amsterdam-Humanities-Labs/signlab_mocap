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
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Retrieve POST data
$glos = isset($_POST['glos']) ? $_POST['glos'] : "";
$action = isset($_POST['action']) ? $_POST['action'] : "";

if (!$glos || !$action) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit();
}

// Determine update query based on action
if ($action === "animationGood") {
    $query = "UPDATE csl_glosses SET pineapple = 1 WHERE glos = ?";
} elseif ($action === "recapture") {
    $query = "UPDATE csl_glosses SET take = NULL, pineapple = 2 WHERE glos = ?";
} else {
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit();
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]);
    exit();
}
$stmt->bind_param("s", $glos);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Execute failed: " . $stmt->error]);
    exit();
}
$stmt->close();
$conn->close();
echo json_encode(["success" => true]);
?>
