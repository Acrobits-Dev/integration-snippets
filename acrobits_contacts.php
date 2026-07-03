<?php
include_once 'helpers.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if (isset($_GET['cloud_username']) && isset($_GET['cloud_id'])) {
    $cloud_username = $_GET['cloud_username'];
    $cloud_id = $_GET['cloud_id'];

    // treat editable and live versions the same
    if (substr($cloud_id, -1) === '*') {
        $cloud_id = rtrim($cloud_id, '*');
    }
    $cloud_id = strtoupper($cloud_id);

    $userFile = findUserList($cloud_id);
    // Open the CSV file with BOM handling
    if (($handle = fopenCSVWithBOMHandling($userFile, "r")) !== FALSE) {
        $columnMapping = getColumnMapping($handle);

        $jsonContacts = array(); // Initialize the final contacts array
        $fallbackContactId = 0; // will be used if username not present
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            
            // Closure to get data value by column name for the current row
            $getVal = function($columnName, $default = null) use ($data, $columnMapping) {
                $columnIndex = $columnMapping[$columnName] ?? null;
                // Check if column exists in mapping and data row has a value at that index
                if ($columnIndex !== null && isset($data[$columnIndex])) { 
                    return $data[$columnIndex];
                }
                return $default;
            };

            // Helper function to check if a value is valid (not empty and not 'null' string)
            $isValidValue = function($value) {
                return !empty($value) && $value !== 'null';
            };

            // Skip if the cloud username matches the one of the user making the request
            $csvCloudUsername = $getVal('cloud_username');
            if ($isValidValue($csvCloudUsername) && $csvCloudUsername === $cloud_username) {
                continue;
            }

            $contact_object = [
                'fname' => $getVal('first_name'),
                'lname' => $getVal('last_name'),
                'displayName' => $getVal('display_name'),
                'contactEntries' => [] // Initialize contactEntries as an empty array
            ];

            // Always ensure contactId - use username if available, otherwise displayName + fallbackContactId
            $username = $getVal('username');
            if ($isValidValue($username)) {
                $contact_object['contactId'] = $username;
            } else {
                $displayName = $getVal('display_name') ?: 'Contact';
                // Strip everything except alphanumeric characters from display name
                $cleanDisplayName = preg_replace('/[^a-zA-Z0-9]/', '', $displayName);
                $contact_object['contactId'] = $cleanDisplayName . '_' . $fallbackContactId++;
            }

            // Add cloud username and network ID if cloud username is present
            if ($isValidValue($csvCloudUsername)) {
                $contact_object['cloudUsername'] = $csvCloudUsername;
                $csvNetworkId = $getVal('networkId');
                $contact_object['networkId'] = $isValidValue($csvNetworkId) ? $csvNetworkId : $cloud_id;
            }
            
            // Add avatar if the column exists and the URL is valid
            $avatarUrl = $getVal('avatar');
            if ($isValidValue($avatarUrl)) {
                $contact_object['avatar'] = $avatarUrl;
                // Determine largeAvatar URL based on source
                $contact_object['largeAvatar'] = (strpos($avatarUrl, 'gravatar.com') !== false)
                    ? $avatarUrl . "?s=200" 
                    : $avatarUrl;
            }

            // Add the primary SIP contact entry if username is present
            if ($isValidValue($username)) {
                $contact_object['contactEntries'][] = [
                    "entryId" => "tel:sip",
                    "label" => "SIP extension",
                    "type" => "tel",
                    "uri" => $username
                ];
            }

            // Add more numbers if they exist and are valid
            $phoneEntryIndex = 1;
            foreach (['phone_number1', 'phone_number2', 'phone_number3', 'phone_number4', 'phone_number5'] as $phoneColumn) {
                $phoneNumber = $getVal($phoneColumn);
                // try to split the column data "label:number" into label (optional) and number
                $phoneNumberParts = explode(':', $phoneNumber);
                $label = $phoneNumberParts[0] ?? "Work";
                $number = $phoneNumberParts[1] ?? $phoneNumber;
                if ($isValidValue($phoneNumber)) {
                    $contact_object['contactEntries'][] = [
                        "entryId" => "tel:phone" . $phoneEntryIndex++,
                        "label" => $label,
                        "type" => "tel",
                        "uri" => $number
                    ];
                }
            }

            // Add email addresses if they exist and are valid
            
            $jsonContacts[] = $contact_object;
        }
        fclose($handle);

        // Return the JSON array
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array("contacts" => $jsonContacts));
    } else {
        // If the CSV file cannot be opened, send an error message
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array("error" => "Unable to open the CSV file"));
    }
} else {
    // If required parameters are not provided, send an error message
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array("error" => "Missing contact username or cloud id"));
}
?>