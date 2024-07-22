<?php
// Database connection settings
$host = '172.18.0.2';
$user = 'root';
$password = 'passw0rd';
$database = 'dmr-database';
$table = 'radioid_data';
$columns = ['RADIO_ID', 'CALLSIGN', 'FIRST_NAME', 'CITY', 'STATE', 'COUNTRY'];

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
        'message' => 'Please provide one or more of the following parameters: f.e. api.php?callsign=pd2emc',
        'parameters' => array_map('strtolower', $columns)
    ]);
    exit;
}

// Display loading message
echo "<html><body><div id='loading'>One moment please, until the data is pulled and ready to be shown...</div></body></html>";

// JavaScript to clear the screen and show the data
echo "
<script>
    setTimeout(function() {
        document.body.innerHTML = '';
    }, 1000);
</script>
";

// Allow some time for the message to be displayed
flush();
ob_flush();
sleep(1);

// Build SQL query
$sql = "SELECT * FROM $table";
$whereClauses = [];
$fetchAll = false;

foreach ($searchParams as $column => $value) {
    if ($value === '*') {
        $fetchAll = true;
        break;
    }
    if (strpos($value, '*') !== false) {
        $value = str_replace('*', '%', escape($value));
        $whereClauses[] = "$column LIKE '$value'";
    } else {
        $value = escape($value);
        $whereClauses[] = "$column = '$value'";
    }
}

if (!$fetchAll && count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

// Function to fetch all results
function fetchAllResults($conn, $sql) {
    $results = [];
    $result = $conn->query($sql);
    if ($result === false) {
        return ['error' => 'Database query failed: ' . $conn->error];
    }
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    return $results;
}

// Fetch all results
$output = fetchAllResults($conn, $sql);

// Close connection
$conn->close();

// Prepare JSON output
$jsonOutput = json_encode($output);

// Debug output
file_put_contents('debug_log.txt', print_r($jsonOutput, true));

?>

<script>
    // Function to clear the screen and display the results
    function displayResults() {
        document.body.innerHTML = '';
        var data = <?php echo json_encode($output); ?>;
        
        // Check if data contains an error
        if (data.error) {
            document.body.textContent = 'Error: ' + data.error;
            return;
        }

        var pre = document.createElement('pre');
        pre.textContent = JSON.stringify(data, null, 2);
        document.body.appendChild(pre);
    }

    // Wait for a moment before displaying the results
    setTimeout(displayResults, 1000);
</script>
