<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');

// // Display no errors or warnings
// error_reporting(0);

include('../mysql_config.php');

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8");

$param = $_GET['param'];

$json_response = array();
if ($param == "randomGloss") {


#count the rows that has take = NULL
$query = "SELECT COUNT(*) as count FROM mocap_data WHERE take IS NULL AND video IS NOT NULL";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $json_response['count'] = $row['count'];
    }
} else {
    echo json_encode("No gloss found");
}

#we want to select rows with take_date equal to today
$query = "SELECT COUNT(*) as count FROM mocap_data WHERE take_date = CURDATE()";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $json_response['taken_today'] = $row['count'];
    }
} else {
    echo json_encode("No gloss found");
}


#we randomize select gloss from mocap_data based on take= NULL
$query = "SELECT glos, video, id, gloss_id FROM mocap_data WHERE take IS NULL AND video IS NOT NULL ORDER BY RAND() LIMIT 1";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $json_response['glos'] = $row['glos'];
        $json_response['video'] = $row['video'];
        $json_response['id'] = $row['id'];
        $json_response['gloss_id'] = $row['gloss_id'];

    }
} else {
    echo json_encode("No gloss found");
}

}
if($param == "pickThreeGlosses")
{
    //we are going to randomize three glosses from mocap_data WHERE pineapple is NULL
    $query = "SELECT glos, video, id, gloss_id FROM mocap_data WHERE pineapple IS NOT NULL ORDER BY RAND() LIMIT 3";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json_response[] = $row;
        }
    } else {
        echo json_encode("No gloss found");
    }
}

if($param == "cslGlosses")
{
    //we are going to select random glos from database csl_glosses
    $query = "SELECT glos, video, id, gloss_id FROM csl_glosses WHERE take IS NULL  ORDER BY id LIMIT 1"; //AND video IS NOT NULL
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json_response['glos'] = $row['glos'];
            $json_response['video'] = "/gebarenoverleg_media/lsc/".$row['video'];
            $json_response['id'] = $row['id'];
            $json_response['gloss_id'] = $row['gloss_id'];

            // print_r($json_response);
        }
    } else {
        echo json_encode("No gloss found");
    }
}


if ($param == "ngtGloss") {


    #count the rows that has take = NULL
    $query = "SELECT COUNT(*) as count FROM mocap_data WHERE take IS NULL AND video IS NOT NULL";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json_response['count'] = $row['count'];
        }
    } else {
        echo json_encode("No gloss found");
    }
    
    #we want to select rows with take_date equal to today
    $query = "SELECT COUNT(*) as count FROM mocap_data WHERE take_date = CURDATE()";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json_response['taken_today'] = $row['count'];
        }
    } else {
        echo json_encode("No gloss found");
    }
    
    
    #we randomize select gloss from mocap_data based on take= NULL
    $query = "SELECT glos, video, id, gloss_id FROM mocap_data WHERE take IS NULL AND video IS NOT NULL ORDER BY RAND() LIMIT 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {

            $video = $row['video'];
 
            $video = "/uploads/".$row['glos'].".mp4";


            $json_response['glos'] = $row['glos'];
            $json_response['video'] = $video;
            $json_response['id'] = $row['id'];
            $json_response['gloss_id'] = $row['gloss_id'];
    
        }
    } else {
        echo json_encode("No gloss found");
    }
    
    }


echo json_encode($json_response, JSON_UNESCAPED_UNICODE);

