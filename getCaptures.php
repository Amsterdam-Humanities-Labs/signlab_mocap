<?php
header('Content-Type: application/json');
include '../mysql_config.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? '';
    
    switch($action) {
        case 'list':
            // Get all captures grouped by date
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    name, 
                    theme, 
                    captured, 
                    captured_time, 
                    DATE(created_at) as capture_date,
                    created_at,
                    has_fbx,
                    has_glb,
                    has_csv,
                    has_mp4,
                    video_url
                FROM captures 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $captures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by date
            $grouped = [];
            foreach($captures as $capture) {
                $date = $capture['capture_date'];
                if (!isset($grouped[$date])) {
                    $grouped[$date] = [];
                }
                $grouped[$date][] = $capture;
            }
            
            echo json_encode(['success' => true, 'data' => $grouped]);
            break;
            
        case 'update_captured':
            // Update captured status
            $id = $_POST['id'] ?? '';
            $captured = $_POST['captured'] ?? 0;
            
            if ($id) {
                $stmt = $pdo->prepare("UPDATE captures SET captured = ?, captured_time = NOW() WHERE id = ?");
                $stmt->execute([$captured, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ID']);
            }
            break;
            
        case 'update_file_status':
            // Update file status (fbx, glb, csv, mp4)
            $id = $_POST['id'] ?? '';
            $file_type = $_POST['file_type'] ?? '';
            $status = $_POST['status'] ?? 0;
            
            if ($id && $file_type) {
                $column = 'has_' . $file_type;
                $stmt = $pdo->prepare("UPDATE captures SET $column = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            }
            break;
            
        case 'update_video_url':
            // Update video URL
            $id = $_POST['id'] ?? '';
            $video_url = $_POST['video_url'] ?? '';
            
            if ($id) {
                $stmt = $pdo->prepare("UPDATE captures SET video_url = ? WHERE id = ?");
                $stmt->execute([$video_url, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>