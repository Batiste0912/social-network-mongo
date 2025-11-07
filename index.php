<?php
require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;

// Configuration des en-têtes CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion de la requête OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inclure les fichiers utilitaires et de routes
require_once __DIR__ . '/utils/utils.php';
require_once __DIR__ . '/routes/users.php';

// Create a database client
$client = new Client('mongodb+srv://db_user:1234567890@next-u-cluster.hc5o3l4.mongodb.net/');


// Get the URI path
user_routes($client);