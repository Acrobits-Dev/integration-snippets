<?php
include_once 'helpers.php';

// Check if both cloud_username and cloud_password are provided in the GET request
if (isset($_GET['cloud_id']) &&  isset($_GET['cloud_username']) && isset($_GET['cloud_password'])) {
    $queryString = $_SERVER['QUERY_STRING'];
    parse_str($queryString, $requestParams);

    $cloud_username = $requestParams['cloud_username'];
    $cloud_password = $requestParams['cloud_password'];
    $cloud_id = $requestParams['cloud_id'];
    $cloud_id = strtoupper($cloud_id);

    // treat editable and live versions the same
    if (substr($cloud_id, -1) === '*') {
        $cloud_id = rtrim($cloud_id, '*');
    }

    $validation = validateUserPassword($cloud_id, $cloud_username, $cloud_password);
    if (!$validation['success']) {
        // Abort if validation fails
        http_response_code(410);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array("error" => $validation['error']));
        exit;
    }
    
    $user_data = findUserDataInUserList($cloud_id, $cloud_username);
    // add cloud_id and cloud_password to user data
    $user_data['cloud_id'] = $cloud_id;
    $user_data['cloud_password'] = $cloud_password;

    $account_template = findExtProvXmlTemplate($cloud_id);

    foreach ($user_data as $key => $value) {
        $placeholder = '{' . $key . '}';
        $account_template = str_replace($placeholder, htmlspecialchars($value), $account_template);
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo $account_template;
} else {
    // If required parameters are not provided, send an error message
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array("error" => "Missing contact name or password"));
}
?>
