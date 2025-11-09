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
    if (isset($segments[2]) && $segments[2] === 'posts' && isset($segments[3]) && $segments[3] === 'count' && !isset($segments[4])) {
        $posts = $client->social_network->posts;
        
        try {
            $count = $posts->countDocuments([]);
            
            sendResponse(200, ['count' => $count], 'Nombre de posts récupéré');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors du comptage des posts: ' . $e->getMessage());
        }
    }
    // GET /stats/posts/{id}/comments/count - Compte le nombre de commentaires d'un post
    elseif (isset($segments[2]) && $segments[2] === 'posts' && isset($segments[3]) && isset($segments[4]) && $segments[4] === 'comments' && isset($segments[5]) && $segments[5] === 'count') {
        $comments = $client->social_network->comments;
        $postId = $segments[3];
        
        try {
            // Essayer avec un ObjectId si c'est un format valide
            if (preg_match('/^[a-f\d]{24}$/i', $postId)) {
                $filter = ['post_id' => new ObjectId($postId)];
            } else {
                // Sinon essayer avec un ID numérique
                $filter = ['post_id' => (int) $postId];
            }
            
            $count = $comments->countDocuments($filter);
            
            sendResponse(200, ['count' => $count], 'Nombre de commentaires récupéré');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors du comptage des commentaires: ' . $e->getMessage());
        }
    }

    // GET /stats/categories/{id}/likes/average - Renvoie la moyenne de likes des posts appartenant à la catégorie demandée.
    elseif (isset($segments[2]) && $segments[2] === 'categories' && isset($segments[3]) && isset($segments[4]) && $segments[4] === 'likes' && isset($segments[5]) && $segments[5] === 'average') {
        $posts = $client->social_network->posts;
        $likes = $client->social_network->likes;
        $categoryId = $segments[3];

        try {
            // Récupérer tous les posts de la catégorie
            $filter = ['category' => $categoryId];
            $postsInCategory = $posts->find($filter)->toArray();

            if (empty($postsInCategory)) {
                sendResponse(200, ['average' => 0], 'Aucun post dans cette catégorie');
                return;
            }

            // Calculer la somme des likes pour chaque post
            $totalLikes = 0;
            foreach ($postsInCategory as $post) {
                $count = $likes->countDocuments(['post_id' => $post['_id']]);
                $totalLikes += $count;
            }

            $average = $totalLikes / count($postsInCategory);

            sendResponse(200, ['average' => $average], 'Moyenne de likes récupérée');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors du calcul de la moyenne: ' . $e->getMessage());
        }
    }

    else {
            sendError(404, 'Endpoint de statistiques non trouvé');
        }
}

