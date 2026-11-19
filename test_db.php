<?php
require_once 'db_connect.php';

echo "Connected successfully to " . $database . "!<br>";

// Example query: Fetch all housing types
$sql = "SELECT * FROM HOUSING_TYPE";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row["type_id"] . " - Type: " . $row["housing_name"] . "<br>";
    }
} else {
    echo "Connected, but HOUSING_TYPE table currently has 0 rows.";
}

$conn->close();
?>