<?php

/**
 * Fonction pour envoyer une réponse JSON
 */
function sendResponse($statusCode, $data = null, $message = null) {
    http_response_code($statusCode);
    $response = [];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Fonction pour gérer les erreurs
 */
function sendError($statusCode, $message) {
    sendResponse($statusCode, null, $message);
}

