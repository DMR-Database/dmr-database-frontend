<?php

// Centralized Database connection settings
$host = '172.18.0.2';
$user = 'root';
$password = 'passw0rd';
$database = 'dmr-database';
$table = 'radioid_data';
$columns = ['RADIO_ID', 'CALLSIGN', 'FIRST_NAME', 'CITY', 'STATE', 'COUNTRY'];

// Function to export data from MariaDB to CSV
function exportMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file, $filter = '') {
    // Establish database connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query to fetch data
    $sql = "SELECT " . implode(', ', $columns) . " FROM $table";
    if (!empty($filter)) {
        $sql .= " WHERE " . $filter;
    }
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Open CSV file for writing
        $csvFile = fopen($output_file, 'w');

        // Write headers to the CSV file
        fputcsv($csvFile, $columns);

        // Fetch and write data rows to the CSV file
        while ($row = $result->fetch_assoc()) {
            fputcsv($csvFile, $row);
        }

        // Close CSV file handle
        fclose($csvFile);

        // Close database connection
        $conn->close();

        return true; // CSV file successfully generated
    } else {
        $conn->close();
        return false; // No rows fetched
    }
}

function exportLimitedMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file, $limit) {

    // Establish database connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query to fetch data
    $sql = "SELECT " . implode(', ', $columns) . " FROM $table LIMIT $limit";
    if (!empty($filter)) {
        $sql .= " WHERE " . $filter;
    }
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Open CSV file for writing
        $csvFile = fopen($output_file, 'w');

        // Write headers to the CSV file
        fputcsv($csvFile, $columns);

        // Fetch and write data rows to the CSV file
        while ($row = $result->fetch_assoc()) {
            fputcsv($csvFile, $row);
        }

        // Close CSV file handle
        fclose($csvFile);

        // Close database connection
        $conn->close();

        return true; // CSV file successfully generated
    } else {
        $conn->close();
        return false; // No rows fetched
    }
}
// Custom function to write a CSV row with CRLF line endings and minimal quoting
function custom_fputcsv($handle, $fields, $delimiter = ',', $enclosure = '"', $escape_char = '\\') {
    $output = '';
    foreach ($fields as $field) {
        if (strpos($field, $delimiter) !== false || strpos($field, $enclosure) !== false || strpos($field, "\n") !== false) {
            // If field contains delimiter, enclosure, or newline, escape the enclosure and wrap in enclosures
            $output .= $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
        } else {
            // Otherwise, add field as-is
            $output .= $field;
        }
        $output .= $delimiter;
    }
    // Remove the trailing delimiter and add a CRLF newline
    $output = rtrim($output, $delimiter) . "\r\n";
    fwrite($handle, $output);
}

// Function to process exported CSV to Anytone Mobile Radio format
function process_to_anytone($csv_filename, $anytone_filename) {
    // Check if the CSV file exists
    if (!file_exists($csv_filename)) {
        echo "$csv_filename not found.\n";
        return false;
    }
    
    // Open input and output files
    $infile = fopen($csv_filename, 'r');
    if ($infile === false) {
        echo "Failed to open $csv_filename for reading.\n";
        return false;
    }
    
    $outfile = fopen($anytone_filename, 'w');
    if ($outfile === false) {
        echo "Failed to open $anytone_filename for writing.\n";
        fclose($infile);
        return false;
    }
    
    // Write headers to the output file
    $fieldnames = ['No.', 'Radio ID', 'Callsign', 'Name', 'City', 'State', 'Country', 'Remarks', 'Call Type', 'Call Alert'];
    custom_fputcsv($outfile, $fieldnames);
    
    // Process each row and write to the output file
    $current_row = 0;
    $first_row = true; // Flag to skip the first row if it contains table headers
    while (($row = fgetcsv($infile)) !== false) {
        // Skip the first row if it's the table headers
        if ($first_row) {
            $first_row = false;
            continue;
        }

        $current_row++;
        
        // Modify fields as needed
        $name = explode(' ', trim($row[2]))[0] ?? ''; // Assuming FIRST_NAME is at index 2
        
        // Prepare data for output
        $output_row = [
            $current_row,
            $row[0], // RADIO_ID
            $row[1], // CALLSIGN
            $name,
            $row[3], // CITY
            $row[4], // STATE
            $row[5], // COUNTRY
            '', // Remarks
            'Private Call',
            'None'
        ];
        
        // Write row to output file
        custom_fputcsv($outfile, $output_row);
    }
    
    // Close file handles
    fclose($infile);
    fclose($outfile);
    
    return true;
}
// 
$output_file = 'DMRIds.dat'; // Output CSV file name
function export_to_pistar($host, $user, $password, $database, $output_file) {

    // Establish database connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }


    // SQL query to fetch specific columns from radioid_data table
    $sql = "SELECT RADIO_ID, CALLSIGN FROM radioid_data";

    // Execute query
    $result = $conn->query($sql);

    // Check for query execution errors
    if (!$result) {
        echo "Error executing query: " . $conn->error . "\n";
        $conn->close();
        return false;
    }

    if ($result->num_rows > 0) {
        // Open CSV file for writing
        $csvFile = fopen($output_file, 'w');

        // Check if file opened successfully
        if (!$csvFile) {
            echo "Error opening CSV file for writing.\n";
            $conn->close();
            return false;
        }

        // Define headers for Pi-Star CSV format
        // $headers = ['Radio ID', 'Callsign'];

        // Write headers to the CSV file
        // fputcsv($csvFile, $headers);

        // Fetch and write data rows to the CSV file
        while ($row = $result->fetch_assoc()) {
            // Adjust the row data if necessary
            $csvRow = [
                $row['RADIO_ID'],
                $row['CALLSIGN']
            ];

            // Write row to the CSV file
            fputcsv($csvFile, $csvRow);
        }

        // Close CSV file handle
        fclose($csvFile);

        // Close database connection
        $conn->close();

        return true; // CSV file successfully generated
    } else {
        $conn->close();
        echo "No rows fetched from the database.\n";
        return false; // No rows fetched
    }
}
//

$table_hamvoip = 'hamvoip_data'; // New table name
$columns_hamvoip = ['Extension', 'Callsign', 'Name']; // Columns for Hamvoip data

// Function to export data from Hamvoip table to CSV
function exportHamvoipDataToCSV($host, $user, $password, $database, $table, $columns, $output_file) {
    // Establish database connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query to fetch data
    $sql = "SELECT " . implode(', ', $columns) . " FROM $table";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Open CSV file for writing
        $csvFile = fopen($output_file, 'w');

        // Write headers to the CSV file
        fputcsv($csvFile, $columns);

        // Fetch and write data rows to the CSV file
        while ($row = $result->fetch_assoc()) {
            fputcsv($csvFile, $row);
        }

        // Close CSV file handle
        fclose($csvFile);

        // Close database connection
        $conn->close();

        return true; // CSV file successfully generated
    } else {
        $conn->close();
        return false; // No rows fetched
    }
}


// Check which download button is clicked
if (isset($_POST['download_all'])) {
    $output_file = 'radioid_export.csv';

    // Generate CSV file for all data
    $csvGenerated = exportMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file);

    // If CSV file is generated, initiate download
    if ($csvGenerated) {
        // Set headers to force download
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . basename($output_file) . '"');
        header('Pragma: no-cache');
        readfile($output_file); // Output file contents
        exit;
    } else {
        echo "Failed to generate CSV file or no data found.";
    }
} elseif (isset($_POST['download_all_anytone_200000'])) {
    $output_file = 'radioid_export.csv';

    // Generate CSV file for limited data
    $csvGenerated = exportLimitedMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file, 200000);

    // If CSV file is generated, process it and initiate download for Anytone and Nytone
    if ($csvGenerated) {
        $anytone_filename = 'output_anytone_all_200000.csv';

        // Process for Anytone
        $processing_result_anytone = process_to_anytone($output_file, $anytone_filename);
        if ($processing_result_anytone) {
            // Set headers to force download for Anytone
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . basename($anytone_filename) . '"');
            header('Pragma: no-cache');
            readfile($anytone_filename); // Output file contents
            exit;
        } else {
            echo "Failed to process CSV to Anytone format.";
        }

    } else {
        echo "Failed to generate CSV file or no data found.";
    }
}elseif (isset($_POST['download_all_anytone'])) {
    $output_file = 'radioid_export.csv';

    // Generate CSV file for all data
    $csvGenerated = exportMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file);

    // If CSV file is generated, process it and initiate download for Anytone and Nytone
    if ($csvGenerated) {
        $anytone_filename = 'output_anytone_all.csv';
        
        // Process for Anytone
        $processing_result_anytone = process_to_anytone($output_file, $anytone_filename);
        if ($processing_result_anytone) {
            // Set headers to force download for Anytone
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . basename($anytone_filename) . '"');
            header('Pragma: no-cache');
            readfile($anytone_filename); // Output file contents
            exit;
        } else {
            echo "Failed to process CSV to Anytone format.";
        }
        
    } else {
        echo "Failed to generate CSV file or no data found.";
    }
} elseif (isset($_POST['download_dutch'])) {
    $output_file = 'radioid_export_nl.csv';

    // Generate CSV file for Dutch data
    $filter = "RADIO_ID LIKE '204%'";
    $csvGenerated = exportMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file, $filter);

    // If CSV file is generated, process it and initiate download for Anytone and Nytone
    if ($csvGenerated) {
        $anytone_filename = 'output_anytone_nl.csv';
        
        // Process for Anytone
        $processing_result_anytone = process_to_anytone($output_file, $anytone_filename);
        if ($processing_result_anytone) {
            // Set headers to force download for Anytone
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . basename($anytone_filename) . '"');
            header('Pragma: no-cache');
            readfile($anytone_filename); // Output file contents
            exit;
        } else {
            echo "Failed to process CSV to Anytone format.";
        }
        
    } else {
        echo "Failed to generate CSV file or no Dutch data found.";
    }
} elseif (isset($_POST['download_filtered'])) {
    // Get the selected filter from the dropdown
    $selected_filter = $_POST['filter'];

    // Create dynamic output file name
    $output_file = 'radioid_export_' . $selected_filter . '.csv';

    // Generate CSV file for filtered data
    $filter = "RADIO_ID LIKE '$selected_filter%'";
    $csvGenerated = exportMariaDBToCSV($host, $user, $password, $database, $table, $columns, $output_file, $filter);

    // If CSV file is generated, process it and initiate download for Anytone and Nytone
    if ($csvGenerated) {
        $anytone_filename = 'output_anytone_' . $selected_filter . '.csv';
        
        // Process for Anytone
        $processing_result_anytone = process_to_anytone($output_file, $anytone_filename);
        if ($processing_result_anytone) {
            // Set headers to force download for Anytone
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . basename($anytone_filename) . '"');
            header('Pragma: no-cache');
            readfile($anytone_filename); // Output file contents
            exit;
        } else {
            echo "Failed to process CSV to Anytone format.";
        }
        
    } else {
        echo "Failed to generate CSV file or no data found.";
    }
} elseif (isset($_POST['download_pistar'])) {
    $output_file_pistar = 'DMRIds.dat';

    // Generate CSV file for Pi-Star data
    // $csvGeneratedPistar = exportPistarDataToCSV($host, $user, $password, $database, $table_pistar, $columns_pistar, $output_file_pistar);
    $csvGeneratedPistar = export_to_pistar($host, $user, $password, $database, $output_file_pistar);

    // If CSV file is generated, initiate download
    if ($csvGeneratedPistar) {
        // Set headers to force download
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . basename($output_file_pistar) . '"');
        header('Pragma: no-cache');
        readfile($output_file_pistar); // Output file contents
        exit;
    } else {
        echo "Failed to generate CSV file or no Pistar data found.";
    }
}elseif (isset($_POST['download_hamvoip'])) {
    $output_file_hamvoip = 'hamvoip_export.csv';

    // Generate CSV file for Hamvoip data
    $csvGeneratedHamvoip = exportHamvoipDataToCSV($host, $user, $password, $database, $table_hamvoip, $columns_hamvoip, $output_file_hamvoip);

    // If CSV file is generated, initiate download
    if ($csvGeneratedHamvoip) {
        // Set headers to force download
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . basename($output_file_hamvoip) . '"');
        header('Pragma: no-cache');
        readfile($output_file_hamvoip); // Output file contents
        exit;
    } else {
        echo "Failed to generate CSV file or no Hamvoip data found.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DMR-Database Download Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #007bff;
            color: white;
            padding: 10px 0;
            text-align: center;
        }
        nav {
            background-color: #0056b3;
            overflow: hidden;
            display: none;
        }
        nav a {
            float: left;
            display: block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }
        nav a:hover {
            background-color: #003f7f;
        }
        .container {
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: none;
        }
        button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        select {
            padding: 10px;
            margin-right: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        label {
            margin-right: 10px;
        }
        h2 {
            color: #333;
        }
        .login-section {
            text-align: center;
            margin-top: 100px;
        }
        input[type="password"] {
            padding: 10px;
            margin-right: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .copyright {
            margin-top: 30px;
            font-size: 0.8rem;
            text-align: center;
        }
        .copyright a {
            color: #007bff;
            text-decoration: none;
        }
        .button-container {
            display: flex;
            justify-content: center;
        }
    </style>
</head>
<body>
    <header>
        <h1>DMR-Database Download Portal</h1>
    </header>
    
    <nav>
        <a href="#home" onclick="showSection('home')">Home</a>
        <a href="#download-all" onclick="showSection('download-all')">Full Database</a>
        <a href="#download-anytone" onclick="showSection('download-anytone')">Anytone Database</a>
        <a href="#download-hd1" onclick="showSection('download-hd1')">Ailunce HD1</a>
        <a href="#download-tytera" onclick="showSection('download-tytera')">Tytera</a>
        <a href="#download-pistar" onclick="showSection('download-pistar')">Pi-Star Database</a>
        <a href="#download-hamvoip" onclick="showSection('download-hamvoip')">Hamvoip Database</a>
    </nav>
    <div class="container">
        <div id="home" class="section">
            <img src="logo.jpeg" alt="DMR Database Team" width="100" height="100">
	    <h2>Welcome to the CSV Download Portal</h2>
            <p>Welcome to the CSV Download Portal, your one-stop destination for accessing a wide range of CSV databases tailored to your specific needs. This portal provides a streamlined and user-friendly interface for generating and downloading various CSV files. Whether you need comprehensive databases for Anytone devices or specific regional data, our portal ensures you have the right tools at your fingertips.</p>
            <p>Choose an option from the menu to proceed.</p>
        </div>
        
        <div id="login" class="login-section">
            <h2>Login</h2>
            <form id="login-form">
                <input type="password" id="password" placeholder="Enter password">
                <button type="submit">Login</button>
            </form>
            <p id="login-error" style="color: red; display: none;">Incorrect password. Please try again.</p>
        </div>

        <div id="download-all" class="section">
            <h2>Generate and Download the Full DMR-Database</h2>
            <form action="" method="post">
                <button type="submit" name="download_all">Download Full CSV</button>
            </form>
        </div>
        
        <div id="download-anytone" class="section">
            <h2>Generate and Download CSV for Anytone AT-D878UV (200000 Users)</h2>
            <form action="" method="post">
                <button type="submit" name="download_all_anytone_200000">Download CSV for Anytone AT-D878UV</button>
            </form>
            <h2>Generate and Download Full CSV for Anytone AT-D878UV II</h2>
            <form action="" method="post">
                <button type="submit" name="download_all_anytone">Download CSV for Anytone AT-D878UV II</button>
            </form>
            <h2>Generate and Download the Dutch DMR-Database for Anytone</h2>
            <form action="" method="post">
                <button type="submit" name="download_dutch">Download Dutch CSV for Anytone</button>
            </form>
            <h2>Generate and Download a Filtered DMR-Database for Anytone</h2>
            <form action="" method="post">
                <label for="filter">Select Country</label>
                <select name="filter" id="filter">
                    <option value="204">Netherlands 204</option>
                    <option value="244">Aaland Islands 244</option>
                    <option value="276">Albania 276</option>
                    <option value="603">Algeria 603</option>
                    <option value="544">American Samoa 544</option>
                    <option value="213">Andorra 213</option>
                    <option value="365">Anguilla 365</option>
                    <option value="344">Antigua and Barbuda 344</option>
                    <option value="214">Argentina Republic 214</option>
                    <option value="283">Armenia 283</option>
                    <option value="363">Aruba 363</option>
                    <option value="658">Ascension Island 658</option>
                    <option value="505">Australia 505</option>
                    <option value="232">Austria 232</option>
                    <option value="400">Azerbaijan 400</option>
                    <option value="364">Bahamas 364</option>
                    <option value="426">Bahrain 426</option>
                    <option value="470">Bangladesh 470</option>
                    <option value="342">Barbados 342</option>
                    <option value="257">Belarus 257</option>
                    <option value="206">Belgium 206</option>
                    <option value="702">Belize 702</option>
                    <option value="350">Bermuda 350</option>
                    <option value="736">Bolivia 736</option>
                    <option value="362">Bonaire 362</option>
                    <option value="218">Bosnia and Hercegovina 218</option>
                    <option value="724">Brazil 724</option>
                    <option value="348">British Virgin Islands 348</option>
                    <option value="528">Brunei Darussalam 528</option>
                    <option value="284">Bulgaria 284</option>
                    <option value="613">Burkina Faso 613</option>
                    <option value="624">Cameroon 624</option>
                    <option value="102">Canada 102</option>
                    <option value="625">Cape Verde 625</option>
                    <option value="346">Cayman Islands 346</option>
                    <option value="623">Central African Republic 623</option>
                    <option value="622">Chad 622</option>
                    <option value="730">Chile 730</option>
                    <option value="454">China 454</option>
                    <option value="732">Colombia 732</option>
                    <option value="548">Cook Islands 548</option>
                    <option value="208">Corsica 208</option>
                    <option value="712">Costa Rica 712</option>
                    <option value="219">Croatia 219</option>
                    <option value="368">Cuba 368</option>
                    <option value="362">Curacao 362</option>
                    <option value="280">Cyprus 280</option>
                    <option value="230">Czech Republic 230</option>
                    <option value="238">Denmark 238</option>
                    <option value="638">Djibouti 638</option>
                    <option value="366">Dominica 366</option>
                    <option value="370">Dominican Republic 370</option>
                    <option value="740">Ecuador 740</option>
                    <option value="602">Egypt 602</option>
                    <option value="706">El Salvador 706</option>
                    <option value="627">Equatorial Guinea 627</option>
                    <option value="657">Falkland Islands 657</option>
                    <option value="542">Fiji Islands 542</option>
                    <option value="244">Finland 244</option>
                    <option value="208">France 208</option>
                    <option value="330">French Antilles 330</option>
                    <option value="632">Gabon 632</option>
                    <option value="628">Gambia 628</option>
                    <option value="226">Georgia 226</option>
                    <option value="262">Germany 262</option>
                    <option value="620">Ghana 620</option>
                    <option value="266">Gibraltar 266</option>
                    <option value="202">Greece 202</option>
                    <option value="334">Grenada 334</option>
                    <option value="352">Guadeloupe 352</option>
                    <option value="535">Guam 535</option>
                    <option value="704">Guatemala 704</option>
                    <option value="611">Guinea 611</option>
                    <option value="632">Guinea Equatorial 632</option>
                    <option value="372">Guyana 372</option>
                    <option value="708">Honduras 708</option>
                    <option value="216">Hungary 216</option>
                    <option value="465">Iceland 465</option>
                    <option value="404">India 404</option>
                    <option value="510">Indonesia 510</option>
                    <option value="432">Iran 432</option>
                    <option value="425">Israel 425</option>
                    <option value="222">Italy 222</option>
                    <option value="338">Jamaica 338</option>
                    <option value="441">Japan 441</option>
                    <option value="416">Jordan 416</option>
                    <option value="401">Kazakhstan 401</option>
                    <option value="639">Kenya 639</option>
                    <option value="470">Korea 470</option>
                    <option value="467">Kuwait 467</option>
                    <option value="437">Kyrgyzstan 437</option>
                    <option value="457">Laos 457</option>
                    <option value="247">Latvia 247</option>
                    <option value="246">Lithuania 246</option>
                    <option value="270">Luxembourg 270</option>
                    <option value="455">Macao 455</option>
                    <option value="294">Macedonia 294</option>
                    <option value="646">Madagascar 646</option>
                    <option value="650">Malawi 650</option>
                    <option value="502">Malaysia 502</option>
                    <option value="472">Maldives 472</option>
                    <option value="610">Mali 610</option>
                    <option value="278">Malta 278</option>
                    <option value="551">Marshall Islands 551</option>
                    <option value="340">Martinique 340</option>
                    <option value="609">Mauritania 609</option>
                    <option value="617">Mauritius 617</option>
                    <option value="334">Mexico 334</option>
                    <option value="550">Micronesia 550</option>
                    <option value="255">Moldova 255</option>
                    <option value="212">Monaco 212</option>
                    <option value="428">Mongolia 428</option>
                    <option value="297">Montenegro 297</option>
                    <option value="514">Myanmar 514</option>
                    <option value="649">Namibia 649</option>
                    <option value="536">Nauru 536</option>
                    <option value="429">Nepal 429</option>
                    <option value="204">Netherlands 204</option>
                    <option value="362">Netherlands Antilles 362</option>
                    <option value="546">New Caledonia 546</option>
                    <option value="530">New Zealand 530</option>
                    <option value="710">Nicaragua 710</option>
                    <option value="614">Niger 614</option>
                    <option value="621">Nigeria 621</option>
                    <option value="257">Northern Ireland 257</option>
                    <option value="242">Norway 242</option>
                    <option value="422">Oman 422</option>
                    <option value="410">Pakistan 410</option>
                    <option value="552">Palau 552</option>
                    <option value="714">Panama 714</option>
                    <option value="537">Papua New Guinea 537</option>
                    <option value="278">Paraguay 278</option>
                    <option value="748">Peru 748</option>
                    <option value="515">Philippines 515</option>
                    <option value="260">Poland 260</option>
                    <option value="268">Portugal 268</option>
                    <option value="330">Puerto Rico 330</option>
                    <option value="424">Qatar 424</option>
                    <option value="226">Republic of Kosovo 226</option>
                    <option value="226">Romania 226</option>
                    <option value="250">Russian Federation 250</option>
                    <option value="635">Rwanda 635</option>
                    <option value="647">Saint Helena 647</option>
                    <option value="356">Saint Lucia 356</option>
                    <option value="358">Saint Vincent and the Grenadines 358</option>
                    <option value="360">Saint Kitts and Nevis 360</option>
                    <option value="549">Samoa 549</option>
                    <option value="292">San Marino 292</option>
                    <option value="608">Sao Tome and Principe 608</option>
                    <option value="420">Saudi Arabia 420</option>
                    <option value="608">Senegal 608</option>
                    <option value="220">Serbia 220</option>
                    <option value="633">Seychelles 633</option>
                    <option value="619">Sierra Leone 619</option>
                    <option value="525">Singapore 525</option>
                    <option value="231">Slovak Republic 231</option>
                    <option value="293">Slovenia 293</option>
                    <option value="540">Solomon Islands 540</option>
                    <option value="637">Somalia 637</option>
                    <option value="655">South Africa 655</option>
                    <option value="659">South Georgia 659</option>
                    <option value="630">South Sudan 630</option>
                    <option value="214">Spain 214</option>
                    <option value="514">Sri Lanka 514</option>
                    <option value="634">Sudan 634</option>
                    <option value="746">Suriname 746</option>
                    <option value="240">Sweden 240</option>
                    <option value="228">Switzerland 228</option>
                    <option value="417">Syria 417</option>
                    <option value="466">Taiwan 466</option>
                    <option value="640">Tanzania 640</option>
                    <option value="520">Thailand 520</option>
                    <option value="615">Togo 615</option>
                    <option value="374">Trinidad and Tobago 374</option>
                    <option value="605">Tunisia 605</option>
                    <option value="286">Turkey 286</option>
                    <option value="438">Turkmenistan 438</option>
                    <option value="376">Turks and Caicos Islands 376</option>
                    <option value="641">Uganda 641</option>
                    <option value="255">Ukraine 255</option>
                    <option value="424">United Arab Emirates 424</option>
                    <option value="226">United Kingdom 226</option>
                    <option value="310">United States 310</option>
                    <option value="748">Uruguay 748</option>
                    <option value="434">Uzbekistan 434</option>
                    <option value="548">Vanuatu 548</option>
                    <option value="734">Venezuela 734</option>
                    <option value="452">Vietnam 452</option>
                    <option value="421">Yemen 421</option>
                    <option value="645">Zambia 645</option>
                    <option value="648">Zimbabwe 648</option>
                </select>
                <button type="submit" name="download_filtered">Download Filtered CSV</button>
            </form>
        </div>

        <div id="download-hd1" class="section">
            <h2>Generate and Download the Full Ailunce HD1 DMR-Database</h2>
            <form action="" method="post">
                <button type="submit" name="download_all_hd1">Download Full CSV for Ailunce HD1 (not done yet)</button>
            </form>
            <h2>Generate and Download the Dutch DMR Database for Ailunce HD1</h2>
            <form action="" method="post">
                <button type="submit" name="download_dutch_hd1">Download Dutch CSV for Ailunce HD1 (not done yet)</button>
            </form>
        </div>


        <div id="download-tytera" class="section">
            <h2>Generate and Download the Full Tytera MD380/390 DMR-Database</h2>
            <form action="" method="post">
                <button type="submit" name="download_all_tytera">Download Full CSV for Tytera MD380/390 (not done yet)</button>
            </form>
            <h2>Generate and Download the Dutch Tytera MD380/390 DMR-Database</h2>
	    <form action="" method="post">
                <button type="submit" name="download_dutch_tytera">Download Dutch CSV for Tytera MD380/390 (not done yet)</button>
            </form>
        </div>

        <div id="download-pistar" class="section">
            <h2>Generate and Download the Full Pi-Star/WPSD DMR-Database</h2>
            <form action="" method="post">
                <button type="submit" name="download_pistar">Download Full CSV for Pi-Star</button>
            </form>
            <br><form action="" method="post">
                <button type="submit" name="download_wpsd">Download Full CSV for WPSD (not done yet)</button>
            </form>
        </div>

<div id="download-hamvoip" class="section">
    <h2>Generate and Download Hamvoip Extension Database</h2>
    <div class="button-container">
        <form action="" method="post">
            <div class="column">
                <button class="download-btn" type="submit" name="download_hamvoip">Download Hamvoip CSV (All Users)</button>
                <button class="download-btn" type="submit" name="download_other">Download Hamvoip CSV (Other na)</button>
                <button class="download-btn" type="submit" name="download_dapnet">Download Hamvoip CSV (Dapnet na)</button>
            </div>
            <div class="column">
                <br><button class="download-btn" type="submit" name="download_fanvil">Download Hamvoip CSV (Fanvil na)</button>
                <button class="download-btn" type="submit" name="download_yealink">Download Hamvoip CSV (Yealink na)</button>
                <button class="download-btn" type="submit" name="download_cisco">Download Hamvoip XML (Cisco na)</button>
            </div>
        </form>
    </div>
</div>


    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var password = document.getElementById('password').value;
            if (password === 'roodwitblauw') {
                document.getElementById('login').style.display = 'none';
                document.querySelector('nav').style.display = 'block';
                showSection('home');
            } else {
                document.getElementById('login-error').style.display = 'block';
            }
        });

        function showSection(sectionId) {
            var sections = document.querySelectorAll('.section');
            sections.forEach(function(section) {
                section.style.display = 'none';
            });
            document.getElementById(sectionId).style.display = 'block';
        }
    </script>
    </script>
    <div class="copyright">
        &copy; 2024 CSV Download Portal by 
DMR User Database Team. All rights reserved. | <a href="https://github.com/DMR-Database" target="_blank">Visit us on GitHub</a>
    </div>
</body>
</html>


