<?php

require_once __DIR__ . '/helpers.php';

if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'), true)) {
    header('Allow: GET, POST');
    reportProvisioningError(405, 'Method not allowed');
}

$requestParams = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$requiredParameters = array('cloud_id', 'cloud_username', 'cloud_password');

foreach ($requiredParameters as $parameter) {
    if (!isset($requestParams[$parameter])
        || !is_string($requestParams[$parameter])
        || $requestParams[$parameter] === ''
    ) {
        reportProvisioningError(400, 'Missing required provisioning parameters');
    }
}

try {
    $cloudId = normalizeCloudId($requestParams['cloud_id']);
} catch (InvalidArgumentException $exception) {
    reportProvisioningError(400, 'Invalid Cloud ID');
}

$cloudUsername = (string) $requestParams['cloud_username'];
$cloudPassword = (string) $requestParams['cloud_password'];

if (!validateUserPassword($cloudId, $cloudUsername, $cloudPassword)) {
    reportProvisioningError(403, 'Invalid username or password');
}

try {
    $userData = findUserDataInUserList($cloudId, $cloudUsername);
    $userData['cloud_id'] = $cloudId;
    $userData['cloud_password'] = $cloudPassword;

    $accountTemplate = findExtProvXmlTemplate($cloudId);
    foreach ($userData as $key => $value) {
        $placeholder = '{' . $key . '}';
        $escapedValue = htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $accountTemplate = str_replace($placeholder, $escapedValue, $accountTemplate);
    }
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    reportProvisioningError(500, 'Provisioning data is unavailable');
}

header('Content-Type: application/xml; charset=utf-8');
echo $accountTemplate;
