<?php

$autoload = realpath('../vendor/autoload.php');
require_once $autoload;

$host = 'localhost';
$root = 'root';
$dbname = 'testdb';

$config_sheets = realpath('../credentials.json');

$tables = ['Electronic', 'Print', 'Direct Mail', 'Fixed Internet', 'New Vehicles',
    'Used Vehicles', 'Used Vehicles - non Internet', 'Internet Total', 'PVR on Internet', 'Store Specific Other'];

function createDb($host, $root, $dbname)
{
    try {
        $db = new PDO("mysql:host=$host", "$root");
        $db->exec("CREATE DATABASE `$dbname`;");
    } catch (PDOException $e) {
    }
}

createDb($host, $root, $dbname);

$conn = new PDO("mysql:host=$host;dbname=$dbname", "$root");

function createTables($conn, $tables) {
    foreach ($tables as $table) {
        try {
            $sql = "CREATE TABLE `$table` (id INTEGER PRIMARY KEY, name VARCHAR(50) NOT NULL, january VARCHAR(50), february VARCHAR(50), march VARCHAR(50), april VARCHAR(50), may VARCHAR(50), june VARCHAR(50),
                july VARCHAR(50), august VARCHAR(50), september VARCHAR(50), october VARCHAR(50), november VARCHAR(50), december VARCHAR(50), total VARCHAR(50))";
            $conn->exec($sql);
        } catch (PDOException $e) {
            continue;
        }
    }
}

function connectionGoogle($config)
{
    $client = new Google\Client();
    $client->setApplicationName("My_Google_App");
    $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($config);
    $service = new Google_Service_Sheets($client);
    $spreadsheetId = "10En6qNTpYNeY_YFTWJ_3txXzvmOA7UxSCrKfKCFfaRw";
    $ranges = 'HVW!A4:N129';
    $params = array(
        'ranges' => $ranges
    );
    return $service->spreadsheets_values->batchGet($spreadsheetId, $params);
}

function getEntries($result, $tables, $entries = 0, $category_entries = array()) {
    foreach ($result['valueRanges'][0]['values'] as $arr) {
        if (in_array($arr[0], $tables)) {
            $entries = 0;
            $key = array_search($arr[0], $tables);
        } elseif ($arr[0] == 'CO-OP') {
            break;
        } elseif ($arr[0] == '') {
            continue;
        } else {
            $entries += 1;
            $category_entries[$tables[$key]] = $entries;
        }
    }
    unset($category_entries[0]);
    return $category_entries;
}

function insertEntries($result, $tables, $callback, $conn, $emp = null) {
    foreach ($result['valueRanges'][0]['values'] as $arr) {
        try {
            if ($arr[0] == 'Internet Total' || $arr[0] == 'PVR on Internet') {
                $sql = "INSERT INTO `$arr[0]` (id, name, january, february, march, april, may, june, 
                     july, august, september, october, november, december, total)
        VALUES (1, '$arr[0]', '$arr[1]', '$arr[2]', '$arr[3]', '$arr[4]', '$arr[5]', '$arr[6]',
                '$arr[7]', '$arr[8]', '$arr[9]', '$arr[10]', '$arr[11]', '$arr[12]', '$arr[13]')";
                $conn->exec($sql);
            }
        } catch (PDOException $e) {
            continue;
        }
        if (in_array($arr[0], $tables) && isset($arr[0])) {
            $table_name = $arr[0];
            $i = 1;
        } elseif ($arr[0] == 'CO-OP') {
            break;
        } elseif ($arr[0] == '') {
            continue;
        } elseif (count($arr) == 1 && !in_array($arr[0], $tables)) {
            try {
                $sql = "INSERT INTO `$table_name` (id, name, january, february, march, april, may, june, 
                         july, august, september, october, november, december, total)
            VALUES ($i, '$arr[0]', '$emp', '$emp', '$emp', '$emp', '$emp', '$emp',
                    '$emp', '$emp', '$emp', '$emp', '$emp', '$emp', '$emp')";
                $conn->exec($sql);
                ++$i;
            } catch (PDOException $e) {
                ++$i;
            }} elseif ((($conn->query("SELECT COUNT(*) FROM `$table_name`")->fetchColumn()) == 0 ||
            ($conn->query("SELECT COUNT(*) FROM `$table_name`")->fetchColumn() !== $callback["$table_name"])) && isset($arr[1])) {
            try {
                $sql = "INSERT INTO `$table_name` (id, name, january, february, march, april, may, june, 
                         july, august, september, october, november, december, total)
            VALUES ($i, '$arr[0]', '$arr[1]', '$arr[2]', '$arr[3]', '$arr[4]', '$arr[5]', '$arr[6]',
                    '$arr[7]', '$arr[8]', '$arr[9]', '$arr[10]', '$arr[11]', '$arr[12]', '$arr[13]')";
                $conn->exec($sql);
                ++$i;
            } catch (PDOException $e) {
                ++$i;
            }
        }
    }
}

function updateEntries($result, $tables, $conn) {
    foreach ($result['valueRanges'][0]['values'] as $arr) {
        try {
            if ($arr[0] == 'Internet Total' || $arr[0] == 'PVR on Internet') {
                $sql = "UPDATE `$arr[0]` SET name = '$arr[0]', january ='$arr[1]', february = '$arr[2]', march = '$arr[3]',
                       april = '$arr[4]', may = '$arr[5]', june = '$arr[6]', july = '$arr[7]', august = '$arr[8]',
                       september = '$arr[9]', october = '$arr[10]', november = '$arr[11]',
                       december = '$arr[12]', total = '$arr[13]' WHERE id = 1";
                $conn->exec($sql);
            }
        } catch (PDOException $e) {
            continue;
        }
        if (in_array($arr[0], $tables) && isset($arr[0])) {
            $table_name = $arr[0];
            $k = 1;
        } elseif ($arr[0] == 'CO-OP') {
            break;
        } elseif ($arr[0] == '') {
            continue;
        } elseif (count($arr) == 1 && !in_array($arr[0], $tables)) {
            for ($i = 1; $i < 15; $i++) {
                $arr[$i] = $arr[$i] ?? null;
            }
            $sql = "UPDATE `$table_name` SET name = '$arr[0]' , january ='$arr[1]', february = '$arr[2]', march = '$arr[3]',
                       april = '$arr[4]', may = '$arr[5]', june = '$arr[6]', july = '$arr[7]', august = '$arr[8]',
                       september = '$arr[9]', october = '$arr[10]', november = '$arr[11]',
                       december = '$arr[12]', total = '$arr[13]' WHERE id = $k";
            $conn->exec($sql);
            ++$k;
        } elseif (($conn->query("SELECT COUNT(*) FROM `$table_name`")->fetchColumn()) !== 0 && isset($arr[1])) {
            $sql = "UPDATE `$table_name` SET name = '$arr[0]', january ='$arr[1]', february = '$arr[2]', march = '$arr[3]',
                       april = '$arr[4]', may = '$arr[5]', june = '$arr[6]', july = '$arr[7]', august = '$arr[8]',
                       september = '$arr[9]', october = '$arr[10]', november = '$arr[11]',
                       december = '$arr[12]', total = '$arr[13]' WHERE id = $k";
            $conn->exec($sql);
            ++$k;
        }
    }
}

createTables($conn, $tables);
$result = connectionGoogle($config_sheets);
insertEntries($result, $tables, getEntries($result, $tables), $conn);
updateEntries($result, $tables, $conn);