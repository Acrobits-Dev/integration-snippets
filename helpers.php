<?php

function reportErrorMessage($errorMessage) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(array("message" => $errorMessage));
    exit();
}

function findUserList($cloud_id) {
    $userFile = '/srv/data/provisioning/users/' . $cloud_id . '.csv';
    if (!file_exists($userFile)) {
        throw new Exception("User list not found at " . $userFile);
    }
    
    return $userFile;
}

/**
 * Open a CSV file for reading, handling UTF-8 BOM if present
 * @param string $filePath Path to the CSV file
 * @param string $mode File open mode (default 'r')
 * @return resource|false File handle positioned after BOM if present
 */
function fopenCSVWithBOMHandling($filePath, $mode = 'r') {
    $handle = fopen($filePath, $mode);
    if (!$handle) {
        return false;
    }
    
    // Only check for BOM when reading
    if (strpos($mode, 'r') !== false || $mode === 'r') {
        // Read first 3 bytes to check for BOM
        $bom = fread($handle, 3);
        
        // If no BOM found, rewind to beginning
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        // If BOM found, file pointer is already positioned after it
    }
    
    return $handle;
}

function getColumnMapping($fileHandle) {
    // Extract column names from the first line of the CSV file
    $columnNames = fgetcsv($fileHandle, 1000, ",");
    // Create an empty array to store the mapping from column name to column index
    $columnMapping = array();

    // Loop through the column names and store the mapping
    foreach ($columnNames as $index => $columnName) {
        $columnMapping[$columnName] = $index;
    }
    return $columnMapping;
}

function validateUserPassword($cloud_id, $cloud_username, $cloud_password) 
{
    try {
        $userCSVFile = findUserList($cloud_id);
        $userData = findUserDataInUserList($cloud_id, $cloud_username);
        $storedCloudPassword = $userData['cloud_password'];
        
        if (strpos($storedCloudPassword, 'bcrypt:') === 0) {
            $storedCloudPassword = substr($storedCloudPassword, 7);
            if (!password_verify($cloud_password, $storedCloudPassword)) {
                return [
                    'success' => false,
                    'error' => "Invalid password"
                ];
            }
        } else {
            // plaintext password, compare directly
            if ($cloud_password != $storedCloudPassword) {
                return [
                    'success' => false,
                    'error' => "Invalid password"
                ];
            }
        }
        
        return [
            'success' => true,
            'error' => null
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function findUserDataInUserList($cloud_id, $cloud_username)
{
    $userCSVFile = findUserList($cloud_id);
    if (($handle = fopenCSVWithBOMHandling($userCSVFile, "r")) !== FALSE) {
        $columnMapping = getColumnMapping($handle);
        $found = false;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Check if the current row's contact name matches the requested one
            if (strtolower($data[$columnMapping['cloud_username']]) == strtolower($cloud_username)) {
                $found = true;
                break;
            }
        }
        fclose($handle);
        if ($found) {
            $userData = array();
            foreach ($columnMapping as $columnName => $columnIndex) {
                $userData[$columnName] = $data[$columnIndex];
            }
            return $userData;
        } else {
            throw new Exception("User not found in user list");
        }
    }
    throw new Exception("Unable to open the CSV file");
}

function findExtProvXmlTemplate($cloud_id) {
    $extProvFile = '/srv/data/provisioning/extProv/' . $cloud_id . '.xml';
    if (!file_exists($extProvFile)) {
        throw new Exception("Ext prov template not found at " . $extProvFile);
    }
    # read the xml file
    $xmlString = file_get_contents($extProvFile);
    return $xmlString;
}

?>
