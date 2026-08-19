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

// **Determine Action Based on 'action' Parameter**
$action = isset($_GET['action']) ? $_GET['action'] : 'fetch_records';

if ($action === 'get_dates') {
    // **Fetch Distinct Dates**
    $dateQuery = "
        SELECT DISTINCT DATE(mf.datetime) AS distinct_date
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
        ORDER BY distinct_date DESC
    ";
    
    $dateResult = $conn->query($dateQuery);
    if (!$dateResult) {
        throw new Exception("Date Query Failed: " . $conn->error);
    }
    
    $dates = [];
    while ($row = $dateResult->fetch_assoc()) {
        $dates[] = $row['distinct_date'];
    }
    
    // **Return the Dates as JSON**
    echo json_encode([
        "success" => true,
        "dates" => $dates
    ]);
    exit();
}

// **Input Validation for Pagination Parameters**
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = ($limit > 0 && $limit <= 100) ? $limit : 10; // Restrict limit to prevent abuse

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = ($page > 0) ? $page : 1;

$offset = ($page - 1) * $limit;

// **Search, Date, and Sort Parameters**
$searchparam = isset($_GET['searchparam']) ? $_GET['searchparam'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$sort_order = isset($_GET['sort']) ? strtoupper($_GET['sort']) : 'DESC';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC'; // Default to DESC if invalid

// **Base Query with JOIN**
$baseQuery = "
    SELECT 
        mf.id AS file_id,
        mf.glos AS file_glos,
        mf.take AS file_take,
        mf.datetime AS file_datetime,
        mf.avatarName AS file_avatarName,
        mf.ll_metadata AS ll_metadata,  -- Added ll_metadata field
        mf.filename AS filename,
        mf.vicon_fbx AS vicon_fbx, 
        mf.vicon_csv AS vicon_csv
    FROM 
        mocap_files mf
    WHERE 
        mf.take IS NOT NULL
";

// **Dynamic Conditions Based on Parameters**
$conditions = [];
$types = ""; // Parameter types for bind_param
$params = []; // Parameters for bind_param

if (!empty($searchparam)) {
    $conditions[] = "mf.glos LIKE CONCAT('%', ?, '%')";
    $types .= "s";
    $params[] = $searchparam;
}

if (!empty($date)) {
    // Validate date format (YYYY-MM-DD)
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        throw new Exception("Invalid date format. Expected YYYY-MM-DD.");
    }
    // Assuming 'mf.datetime' is of DATE or DATETIME type
    $conditions[] = "DATE(mf.datetime) = ?";
    $types .= "s";
    $params[] = $date;
}

if (!empty($conditions)) {
    $baseQuery .= " AND " . implode(" AND ", $conditions);
}

// **Ordering and Pagination**
$baseQuery .= " ORDER BY mf.id $sort_order LIMIT ? OFFSET ?";

// **Add types and parameters for LIMIT and OFFSET**
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

// **Prepare the Statement**
$stmt = $conn->prepare($baseQuery);
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}

// **Bind Parameters Dynamically**
if (!empty($types)) {
    // Create a dynamic array of references
    $bind_names[] = $types;
    for ($i=0; $i<count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

// **Execute the Statement**
if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
}

// **Get the Result**
$result = $stmt->get_result();

// **Process the Data**
$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Extract fields from mocap_files (alias: mf)
        $file_id = $row['file_id'];
        $file_glos = $row['file_glos'];
        $file_take = $row['file_take'];
        $file_datetime = $row['file_datetime'];
        $file_avatarName = $row['file_avatarName'];
        $filename = $row['filename'];
        $ll_metadata = $row['ll_metadata'];  // Retrieved ll_metadata field
        $vicon_fbx = $row['vicon_fbx'];
        $vicon_csv = $row['vicon_csv'];

        // Replace .fbx with .glb in filename
        $filename = str_replace(".fbx", ".glb", $filename);

        // Extract fields from mocap_data (alias: md)
        $data_id = $row['file_id'];
        $data_take = $row['file_take'];
        $data_glos = $row['file_glos'];

        // **Construct the video_url**
        $video_url = "https://signcollect.nl/uploads/" . $file_glos . ".mp4";

        // **Prepare the Record**
        $record = [
            "file_id" => $file_id,
            "glos" => $file_glos,
            "take" => $file_take,
            "datetime" => $file_datetime,
            "avatarName" => $file_avatarName,
            "video_url" => $video_url,
            "filename" => $filename,
            "ll_metadata" => $ll_metadata,             // Include the metadata filename
            'vicon_fbx' => $vicon_fbx,
            'vicon_csv' => $vicon_csv,
        ];

        $data[] = $record;
    }
}

// **Fetch Total Count and unique_glos_count for Pagination Only if Page is 1**
if ($page === 1) {
    // **Total Count Query**
    $totalQuery = "
        SELECT COUNT(*) AS total
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
    ";

    // **Dynamic Conditions for Total Count Query**
    $conditions_total = [];
    $types_total = ""; // Parameter types for bind_param
    $params_total = []; // Parameters for bind_param

    if (!empty($searchparam)) {
        $conditions_total[] = "mf.glos LIKE CONCAT('%', ?, '%')";
        $types_total .= "s";
        $params_total[] = $searchparam;
    }

    if (!empty($date)) {
        $conditions_total[] = "DATE(mf.datetime) = ?";
        $types_total .= "s";
        $params_total[] = $date;
    }

    if (!empty($conditions_total)) {
        $totalQuery .= " AND " . implode(" AND ", $conditions_total);
    }

    // **Prepare the Total Count Statement**
    $totalStmt = $conn->prepare($totalQuery);
    if (!$totalStmt) {
        throw new Exception("Prepare total count failed: " . $conn->error);
    }

    // **Bind Parameters Dynamically for Total Count**
    if (!empty($types_total)) {
        // Create a dynamic array of references
        $bind_names_total[] = $types_total;
        for ($i=0; $i<count($params_total); $i++) {
            $bind_name = 'bind_total_' . $i;
            $$bind_name = $params_total[$i];
            $bind_names_total[] = &$$bind_name;
        }
        call_user_func_array(array($totalStmt, 'bind_param'), $bind_names_total);
    }

    // **Execute the Total Count Statement**
    if (!$totalStmt->execute()) {
        throw new Exception("Execute total count failed: " . $totalStmt->error);
    }

    $totalResult = $totalStmt->get_result();
    $total = 0;
    if ($totalResult->num_rows > 0) {
        $totalRow = $totalResult->fetch_assoc();
        $total = (int)$totalRow['total'];
    }

    $totalStmt->close();

    // **Fetch unique_glos_count**
    $uniqueGlosCountQuery = "
        SELECT COUNT(DISTINCT mf.glos) AS unique_glos_count
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
    ";

    // **Apply the Same Conditions as Total Count**
    if (!empty($conditions_total)) {
        $uniqueGlosCountQuery .= " AND " . implode(" AND ", $conditions_total);
    }

    // **Prepare the unique_glos_count Statement**
    $uniqueGlosStmt = $conn->prepare($uniqueGlosCountQuery);
    if (!$uniqueGlosStmt) {
        throw new Exception("Prepare unique_glos_count failed: " . $conn->error);
    }

    // **Bind Parameters Dynamically for unique_glos_count**
    if (!empty($types_total)) {
        // Create a dynamic array of references
        $bind_names_unique[] = $types_total;
        for ($i=0; $i<count($params_total); $i++) {
            $bind_name = 'bind_unique_' . $i;
            $$bind_name = $params_total[$i];
            $bind_names_unique[] = &$$bind_name;
        }
        call_user_func_array(array($uniqueGlosStmt, 'bind_param'), $bind_names_unique);
    }

    // **Execute the unique_glos_count Statement**
    if (!$uniqueGlosStmt->execute()) {
        throw new Exception("Execute unique_glos_count failed: " . $uniqueGlosStmt->error);
    }

    $uniqueGlosResult = $uniqueGlosStmt->get_result();
    $unique_glos_count = 0;
    if ($uniqueGlosResult->num_rows > 0) {
        $uniqueGlosRow = $uniqueGlosResult->fetch_assoc();
        $unique_glos_count = (int)$uniqueGlosRow['unique_glos_count'];
    }

    $uniqueGlosStmt->close();

    // **Fetch Counts for ll_metadata, vicon_fbx, and vicon_csv**
    // We'll count the number of records where each field is NOT NULL and not empty

    // **ll_metadata Count**
    $llMetadataCountQuery = "
        SELECT COUNT(*) AS count_ll_metadata
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
    ";

    if (!empty($conditions_total)) {
        $llMetadataCountQuery .= " AND " . implode(" AND ", $conditions_total);
    }

    $llMetadataCountQuery .= " AND mf.ll_metadata IS NOT NULL AND TRIM(mf.ll_metadata) <> ''";

    $llMetadataStmt = $conn->prepare($llMetadataCountQuery);
    if (!$llMetadataStmt) {
        throw new Exception("Prepare count_ll_metadata failed: " . $conn->error);
    }

    // **Bind Parameters Dynamically for ll_metadata Count**
    if (!empty($types_total)) {
        // Create a dynamic array of references
        $bind_names_ll[] = $types_total;
        for ($i=0; $i<count($params_total); $i++) {
            $bind_name = 'bind_ll_' . $i;
            $$bind_name = $params_total[$i];
            $bind_names_ll[] = &$$bind_name;
        }
        call_user_func_array(array($llMetadataStmt, 'bind_param'), $bind_names_ll);
    }

    // **Execute the ll_metadata Count Statement**
    if (!$llMetadataStmt->execute()) {
        throw new Exception("Execute count_ll_metadata failed: " . $llMetadataStmt->error);
    }

    $llMetadataResult = $llMetadataStmt->get_result();
    $count_ll_metadata = 0;
    if ($llMetadataResult->num_rows > 0) {
        $llMetadataRow = $llMetadataResult->fetch_assoc();
        $count_ll_metadata = (int)$llMetadataRow['count_ll_metadata'];
    }

    $llMetadataStmt->close();

    // **vicon_fbx Count**
    $viconFbxCountQuery = "
        SELECT COUNT(*) AS count_vicon_fbx
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
    ";

    if (!empty($conditions_total)) {
        $viconFbxCountQuery .= " AND " . implode(" AND ", $conditions_total);
    }

    $viconFbxCountQuery .= " AND mf.vicon_fbx IS NOT NULL AND TRIM(mf.vicon_fbx) <> ''";

    $viconFbxStmt = $conn->prepare($viconFbxCountQuery);
    if (!$viconFbxStmt) {
        throw new Exception("Prepare count_vicon_fbx failed: " . $conn->error);
    }

    // **Bind Parameters Dynamically for vicon_fbx Count**
    if (!empty($types_total)) {
        // Create a dynamic array of references
        $bind_names_fbx[] = $types_total;
        for ($i=0; $i<count($params_total); $i++) {
            $bind_name = 'bind_fbx_' . $i;
            $$bind_name = $params_total[$i];
            $bind_names_fbx[] = &$$bind_name;
        }
        call_user_func_array(array($viconFbxStmt, 'bind_param'), $bind_names_fbx);
    }

    // **Execute the vicon_fbx Count Statement**
    if (!$viconFbxStmt->execute()) {
        throw new Exception("Execute count_vicon_fbx failed: " . $viconFbxStmt->error);
    }

    $viconFbxResult = $viconFbxStmt->get_result();
    $count_vicon_fbx = 0;
    if ($viconFbxResult->num_rows > 0) {
        $viconFbxRow = $viconFbxResult->fetch_assoc();
        $count_vicon_fbx = (int)$viconFbxRow['count_vicon_fbx'];
    }

    $viconFbxStmt->close();

    // **vicon_csv Count**
    $viconCsvCountQuery = "
        SELECT COUNT(*) AS count_vicon_csv
        FROM mocap_files mf
        WHERE mf.take IS NOT NULL
    ";

    if (!empty($conditions_total)) {
        $viconCsvCountQuery .= " AND " . implode(" AND ", $conditions_total);
    }

    $viconCsvCountQuery .= " AND mf.vicon_csv IS NOT NULL AND TRIM(mf.vicon_csv) <> ''";

    $viconCsvStmt = $conn->prepare($viconCsvCountQuery);
    if (!$viconCsvStmt) {
        throw new Exception("Prepare count_vicon_csv failed: " . $conn->error);
    }

    // **Bind Parameters Dynamically for vicon_csv Count**
    if (!empty($types_total)) {
        // Create a dynamic array of references
        $bind_names_csv[] = $types_total;
        for ($i=0; $i<count($params_total); $i++) {
            $bind_name = 'bind_csv_' . $i;
            $$bind_name = $params_total[$i];
            $bind_names_csv[] = &$$bind_name;
        }
        call_user_func_array(array($viconCsvStmt, 'bind_param'), $bind_names_csv);
    }

    // **Execute the vicon_csv Count Statement**
    if (!$viconCsvStmt->execute()) {
        throw new Exception("Execute count_vicon_csv failed: " . $viconCsvStmt->error);
    }

    $viconCsvResult = $viconCsvStmt->get_result();
    $count_vicon_csv = 0;
    if ($viconCsvResult->num_rows > 0) {
        $viconCsvRow = $viconCsvResult->fetch_assoc();
        $count_vicon_csv = (int)$viconCsvRow['count_vicon_csv'];
    }

    $viconCsvStmt->close();

    // **Prepare the Response with Pagination and Additional Counts**
    $response = [
        "success" => true,
        "data" => $data,
        "pagination" => [
            "current_page" => $page,
            "limit" => $limit,
            "total_records" => $total,
            "total_pages" => ceil($total / $limit)
        ],
        "counts" => [
            "unique_glos_count" => $unique_glos_count, // Total count of unique glos
            "count_all" => $total,
            "count_ll_metadata" => $count_ll_metadata, // Count of filled ll_metadata
            "count_vicon_fbx" => $count_vicon_fbx,     // Count of filled vicon_fbx
            "count_vicon_csv" => $count_vicon_csv      // Count of filled vicon_csv
        ]
    ];
} else {
    // **Prepare the Response Without Counts**
    $response = [
        "success" => true,
        "data" => $data,
        "pagination" => [
            "current_page" => $page,
            "limit" => $limit,
            "total_records" => null, // Not provided
            "total_pages" => null
        ],
        "counts" => [
            "unique_glos_count" => 0,
            "count_all" => 0,
            "count_ll_metadata" => 0,
            "count_vicon_fbx" => 0,
            "count_vicon_csv" => 0
        ]
    ];
}

$stmt->close();

// **Close the Database Connection**
$conn->close();

// **Return the Data as JSON**
echo json_encode($response);
?>
