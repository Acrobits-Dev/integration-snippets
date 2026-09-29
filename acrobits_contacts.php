<?php

require_once __DIR__ . '/helpers.php';

if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'), true)) {
    header('Allow: GET, POST');
    reportJsonError(405, 'Method not allowed');
}

$requestParams = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$requiredParameters = array('cloud_id', 'cloud_username', 'cloud_password');

foreach ($requiredParameters as $parameter) {
    if (!isset($requestParams[$parameter])
        || !is_string($requestParams[$parameter])
        || $requestParams[$parameter] === ''
    ) {
        reportJsonError(400, 'Missing required contact parameters');
    }
}

try {
    $cloudId = normalizeCloudId($requestParams['cloud_id']);
} catch (InvalidArgumentException $exception) {
    reportJsonError(400, 'Invalid Cloud ID');
}

$cloudUsername = (string) $requestParams['cloud_username'];
$cloudPassword = (string) $requestParams['cloud_password'];

if (!validateUserPassword($cloudId, $cloudUsername, $cloudPassword)) {
    reportJsonError(403, 'Invalid username or password');
}

$handle = false;

try {
    $handle = fopenCSVWithBOMHandling(findUserList($cloudId));
    if ($handle === false) {
        throw new RuntimeException('Unable to open the user CSV');
    }

    $columnMapping = getColumnMapping($handle);
    requireColumns($columnMapping, array(
        'cloud_username',
        'username',
        'display_name',
        'first_name',
        'last_name'
    ));

    $jsonContacts = array();
    $seenContactIds = array();

    while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $getValue = function ($columnName, $default = '') use ($data, $columnMapping) {
            if (!array_key_exists($columnName, $columnMapping)) {
                return $default;
            }

            $columnIndex = $columnMapping[$columnName];
            return isset($data[$columnIndex]) ? $data[$columnIndex] : $default;
        };

        $isValidValue = function ($value) {
            return $value !== null && $value !== '' && strcasecmp($value, 'null') !== 0;
        };

        $csvCloudUsername = $getValue('cloud_username');
        if ($isValidValue($csvCloudUsername)
            && strcasecmp($csvCloudUsername, $cloudUsername) === 0
        ) {
            continue;
        }

        $username = $getValue('username');
        $contactId = $isValidValue($username) ? $username : $csvCloudUsername;
        if (!$isValidValue($contactId) || isset($seenContactIds[$contactId])) {
            error_log('Skipping contact with a missing or duplicate contactId');
            continue;
        }
        $seenContactIds[$contactId] = true;

        $contact = array(
            'fname' => $getValue('first_name'),
            'lname' => $getValue('last_name'),
            'displayName' => $getValue('display_name'),
            'contactId' => $contactId,
            'contactEntries' => array()
        );

        if ($isValidValue($csvCloudUsername)) {
            $contact['cloudUsername'] = $csvCloudUsername;
            $csvNetworkId = $getValue('networkId');
            $contact['networkId'] = $isValidValue($csvNetworkId) ? $csvNetworkId : $cloudId;
        }

        $avatarUrl = $getValue('avatar');
        if ($isValidValue($avatarUrl)) {
            $contact['avatar'] = $avatarUrl;
            $contact['largeAvatar'] = strpos($avatarUrl, 'gravatar.com') !== false
                ? $avatarUrl . '?s=200'
                : $avatarUrl;
        }

        if ($isValidValue($username)) {
            $contact['contactEntries'][] = array(
                'entryId' => 'tel:sip',
                'label' => 'SIP extension',
                'type' => 'tel',
                'uri' => $username
            );
        }

        $phoneEntryIndex = 1;
        foreach (array(
            'phone_number1',
            'phone_number2',
            'phone_number3',
            'phone_number4',
            'phone_number5'
        ) as $phoneColumn) {
            $phoneNumber = $getValue($phoneColumn);
            if (!$isValidValue($phoneNumber)) {
                continue;
            }

            $phoneNumberParts = explode(':', $phoneNumber, 2);
            $hasLabel = count($phoneNumberParts) === 2;
            $label = $hasLabel ? trim($phoneNumberParts[0]) : 'Work';
            $number = trim($hasLabel ? $phoneNumberParts[1] : $phoneNumberParts[0]);

            if ($number === '' || $number === $username) {
                continue;
            }

            $contact['contactEntries'][] = array(
                'entryId' => 'tel:phone' . $phoneEntryIndex++,
                'label' => $label !== '' ? $label : 'Work',
                'type' => 'tel',
                'uri' => $number
            );
        }

        $jsonContacts[] = $contact;
    }

    fclose($handle);
    $handle = false;
} catch (RuntimeException $exception) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    error_log($exception->getMessage());
    reportJsonError(500, 'Contact data is unavailable');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('contacts' => $jsonContacts), JSON_INVALID_UTF8_SUBSTITUTE);
