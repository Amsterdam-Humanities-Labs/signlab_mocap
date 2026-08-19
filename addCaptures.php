<?php
header('Content-Type: application/json');
include '../mysql_config.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add_single':
                // Add a single capture
                $name = $_POST['name'] ?? '';
                $theme = $_POST['theme'] ?? '';
                $video_url = $_POST['video_url'] ?? '';
                
                if ($name && $theme) {
                    $stmt = $pdo->prepare("
                        INSERT INTO captures (name, theme, captured, has_fbx, has_glb, has_csv, has_mp4, video_url, created_at) 
                        VALUES (?, ?, 0, 0, 0, 0, 0, ?, NOW())
                    ");
                    $stmt->execute([$name, $theme, $video_url]);
                    echo json_encode(['success' => true, 'message' => 'Capture added successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Name and theme are required']);
                }
                break;
                
            case 'add_batch':
                // Add multiple captures from a list
                $names = $_POST['names'] ?? '';
                $theme = $_POST['theme'] ?? '';
                $video_urls = $_POST['video_urls'] ?? '';
                
                if ($names && $theme) {
                    $namesList = array_filter(array_map('trim', explode("\n", $names)));
                    $videoUrlsList = array_map('trim', explode("\n", $video_urls));
                    $success_count = 0;
                    
                    foreach ($namesList as $index => $name) {
                        if (!empty($name)) {
                            // Get corresponding video URL by index, or empty string if not available
                            $video_url = isset($videoUrlsList[$index]) ? $videoUrlsList[$index] : '';
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO captures (name, theme, captured, has_fbx, has_glb, has_csv, has_mp4, video_url, created_at) 
                                VALUES (?, ?, 0, 0, 0, 0, 0, ?, NOW())
                            ");
                            $stmt->execute([$name, $theme, $video_url]);
                            $success_count++;
                        }
                    }
                    
                    echo json_encode(['success' => true, 'message' => "$success_count captures added successfully"]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Names and theme are required']);
                }
                break;
                
            case 'delete':
                // Delete a capture
                $id = $_POST['id'] ?? '';
                
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM captures WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true, 'message' => 'Capture deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID is required']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>