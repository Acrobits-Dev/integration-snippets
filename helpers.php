<?php

function provisioningDataDirectory()
{
    $configuredDirectory = getenv('ACROBITS_PROVISIONING_DATA_DIR');
    $dataDirectory = $configuredDirectory !== false && $configuredDirectory !== ''
        ? $configuredDirectory
        : '/srv/data/provisioning';

    return rtrim($dataDirectory, '/');
}

function normalizeCloudId($cloudId)
{
    $cloudId = strtoupper(rtrim((string) $cloudId, '*'));

    if ($cloudId === '' || preg_match('/\A[A-Z0-9_-]+\z/', $cloudId) !== 1) {
        throw new InvalidArgumentException('Invalid Cloud ID');
    }

    return $cloudId;
}

function reportJsonError($statusCode, $message)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $message), JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

function reportProvisioningError($statusCode, $message)
{
    http_response_code($statusCode);
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<error><message>'
        . htmlspecialchars($message, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8')
        . '</message></error>';
    exit();
}

function findUserList($cloudId)
{
    $cloudId = normalizeCloudId($cloudId);
    $userFile = provisioningDataDirectory() . '/users/' . $cloudId . '.csv';

    if (!is_file($userFile) || !is_readable($userFile)) {
        throw new RuntimeException('User list is unavailable');
    }

    return $userFile;
}

/**
 * Open a CSV file for reading, handling a UTF-8 BOM if present.
 *
 * @param string $filePath Path to the CSV file.
 * @param string $mode File open mode (default "r").
 * @return resource|false File handle positioned after the BOM if present.
 */
function fopenCSVWithBOMHandling($filePath, $mode = 'r')
{
    $handle = fopen($filePath, $mode);
    if ($handle === false) {
        return false;
    }

    if (strpos($mode, 'r') !== false) {
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
    }

    return $handle;
}

function getColumnMapping($fileHandle)
{
    $columnNames = fgetcsv($fileHandle, 0, ',', '"', '\\');
    if ($columnNames === false) {
        throw new RuntimeException('The user CSV is empty');
    }

    $columnMapping = array();
    foreach ($columnNames as $index => $columnName) {
        $columnName = trim($columnName);
        if ($columnName !== '') {
            $columnMapping[$columnName] = $index;
        }
    }

    return $columnMapping;
}

function requireColumns($columnMapping, $requiredColumns)
{
    foreach ($requiredColumns as $columnName) {
        if (!array_key_exists($columnName, $columnMapping)) {
            throw new RuntimeException('The user CSV is missing a required column');
        }
    }
}

function validateUserPassword($cloudId, $cloudUsername, $cloudPassword)
{
    try {
        $userData = findUserDataInUserList($cloudId, $cloudUsername);
    } catch (RuntimeException $exception) {
        error_log($exception->getMessage());
        return false;
    }

    $storedCloudPassword = $userData['cloud_password'];
    if (strpos($storedCloudPassword, 'bcrypt:') === 0) {
        return password_verify($cloudPassword, substr($storedCloudPassword, 7));
    }

    return hash_equals($storedCloudPassword, (string) $cloudPassword);
}

function findUserDataInUserList($cloudId, $cloudUsername)
{
    $handle = fopenCSVWithBOMHandling(findUserList($cloudId));
    if ($handle === false) {
        throw new RuntimeException('Unable to open the user CSV');
    }

    try {
        $columnMapping = getColumnMapping($handle);
        requireColumns($columnMapping, array('cloud_username', 'cloud_password'));

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $usernameIndex = $columnMapping['cloud_username'];
            if (!isset($data[$usernameIndex])) {
                continue;
            }

            if (strcasecmp($data[$usernameIndex], (string) $cloudUsername) === 0) {
                $userData = array();
                foreach ($columnMapping as $columnName => $columnIndex) {
                    $userData[$columnName] = isset($data[$columnIndex]) ? $data[$columnIndex] : '';
                }
                return $userData;
            }
        }
    } finally {
        fclose($handle);
    }

    throw new RuntimeException('User not found in user list');
}

function findExtProvXmlTemplate($cloudId)
{
    $cloudId = normalizeCloudId($cloudId);
    $templateFile = provisioningDataDirectory() . '/extProv/' . $cloudId . '.xml';

    if (!is_file($templateFile) || !is_readable($templateFile)) {
        throw new RuntimeException('External provisioning template is unavailable');
    }

    $xmlString = file_get_contents($templateFile);
    if ($xmlString === false) {
        throw new RuntimeException('Unable to read the external provisioning template');
    }

    return $xmlString;
}
