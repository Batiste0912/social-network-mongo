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
require_once __DIR__ . '/routes/comments.php';
require_once __DIR__ . '/routes/follows.php';
require_once __DIR__ . '/routes/likes.php';
require_once __DIR__ . '/routes/categories.php';
require_once __DIR__ . '/routes/posts.php';

// Create a database client
$client = new Client('mongodb+srv://db_user:1234567890@next-u-cluster.hc5o3l4.mongodb.net/');

// Get the URI path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Router basique
if (isset($segments[0]) && $segments[0] === 'api' && isset($segments[1])) {
    if ($segments[1] === 'users') {
        user_routes($client);
    } elseif ($segments[1] === 'comments') {
        comments_routes($client);
    } elseif ($segments[1] === 'follows') {
        follows_routes($client);
    } elseif ($segments[1] === 'likes') {
        likes_routes($client);
    } elseif ($segments[1] === 'categories') {
        categories_routes($client);
    } elseif ($segments[1] === 'posts') {
        posts_routes($client);
    } else {
        sendError(404, 'Route non trouvée');
    }
} else {
    // Route par défaut
    sendError(404, 'Route non trouvée');
}
