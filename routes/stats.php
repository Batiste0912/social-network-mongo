<?php

use MongoDB\BSON\ObjectId;

function stats_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/stats
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'stats') {
        sendError(404, 'Route stats non trouvée');
    }

    // Vérifier que la méthode est GET
    if ($method !== 'GET') {
        sendError(405, 'Méthode non autorisée');
    }

    // GET /stats/posts/count - Compte le nombre de posts
    if (isset($segments[2]) && $segments[2] === 'posts' && isset($segments[3]) && $segments[3] === 'count') {
        $posts = $client->social_network->posts;
        
        try {
            $count = $posts->countDocuments([]);
            
            sendResponse(200, ['count' => $count], 'Nombre de posts récupéré');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors du comptage des posts: ' . $e->getMessage());
        }
    } else {
        sendError(404, 'Endpoint de statistiques non trouvé');
    }
}

