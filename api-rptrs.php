<?php
// Database connection settings
$host = '172.18.0.2';
$user = 'root';
$password = 'passw0rd';
$database = 'dmr-database';
$table = 'rptrs';
$columns = [
    'locator', 'id', 'callsign', 'city', 'state', 'country',
    'frequency', 'color_code', 'offset', 'assigned', 'ts_linked',
    'trustee', 'map_info', 'map', 'ipsc_network'
];

// Define the API password
$apiPassword = 'passw0rd';

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to escape SQL special characters
function escape($value) {
    global $conn;
    return $conn->real_escape_string($value);
}

// Check for password
if (!isset($_GET['key']) || $_GET['key'] !== $apiPassword) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access. Please provide a valid password.']);
    exit;
}

// Fetch query parameters
$searchParams = [];
foreach ($columns as $column) {
    if (isset($_GET[strtolower($column)])) {
        $searchParams[$column] = $_GET[strtolower($column)];
    }
}

// Display overview of options if no parameters are given
if (empty($searchParams)) {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Please provide one or more of the following parameters:',
        'parameters' => array_map('strtolower', $columns)
    ]);
    exit;
}

// Build SQL query
$sql = "SELECT * FROM $table";
$whereClauses = [];

foreach ($searchParams as $column => $value) {
    if (strpos($value, '*') !== false) {
        $value = str_replace('*', '%', escape($value));
        $whereClauses[] = "$column LIKE '$value'";
    } else {
        $value = escape($value);
        $whereClauses[] = "$column = '$value'";
    }
}

if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

// Execute query
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $output = [];
    while ($row = $result->fetch_assoc()) {
        $output[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($output);
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}

// Close connection
$conn->close();
?>

