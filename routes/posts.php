<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function posts_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/posts
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'posts') {
        sendError(404, 'Route posts non trouvée');
    }

    // ID attendu après /api/posts/{id}
    $id = $segments[2] ?? null;

    // Get posts collection
    $posts = $client->social_network->posts;

    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);

    // Helper: convertir une date ISO8601 en UTCDateTime (millisecondes)
    $toUTCDateTime = function ($iso) {
        try {
            $dt = new DateTime($iso);
        } catch (Exception $e) {
            return null;
        }
        // secondes * 1000 + ms
        $ms = ((int)$dt->format('U')) * 1000 + (int)($dt->format('u') / 1000);
        return new UTCDateTime($ms);
    };

    // Helper: convertir UTCDateTime en ISO8601
    $fromUTCDateTime = function ($utc) {
        if (!($utc instanceof UTCDateTime)) return null;
        $dt = $utc->toDateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format(DATE_ATOM);
    };

    // CREATE - Créer un post
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['content', 'user_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        // Valider les ObjectId/int pour user_id et category_id
        try {
            // user_id peut être un ObjectId ou un int
            if (preg_match('/^[a-f\d]{24}$/i', $input['user_id'])) {
                $userObjectId = new ObjectId($input['user_id']);
            } else {
                $userObjectId = (int) $input['user_id'];
            }
        } catch (Exception $e) {
            sendError(400, 'user_id invalide');
        }

        // category_id est optionnel
        $categoryId = null;
        if (!empty($input['category_id'])) {
            try {
                if (preg_match('/^[a-f\d]{24}$/i', $input['category_id'])) {
                    $categoryId = new ObjectId($input['category_id']);
                } else {
                    $categoryId = (int) $input['category_id'];
                }
            } catch (Exception $e) {
                sendError(400, 'category_id invalide');
            }
        }

        // Ajouter la date si elle n'existe pas
        if (empty($input['date'])) {
            $utcNow = new UTCDateTime((int)(microtime(true) * 1000));
        } else {
            $utcNow = $toUTCDateTime($input['date']);
            if ($utcNow === null) sendError(400, 'Format de date invalide');
        }

        // Préparer le document
        $document = [
            'content' => $input['content'],
            'user_id' => $userObjectId,
            'date' => $utcNow,
        ];

        if ($categoryId !== null) {
            $document['category_id'] = $categoryId;
        }

        $result = $posts->insertOne($document);

        // Préparer la réponse en convertissant les ids et la date
        $response = [
            '_id' => (string) $result->getInsertedId(),
            'content' => $document['content'],
            'user_id' => is_object($document['user_id']) ? (string) $document['user_id'] : $document['user_id'],
            'date' => $fromUTCDateTime($document['date']),
        ];

        if (isset($document['category_id'])) {
            $response['category_id'] = is_object($document['category_id']) ? (string) $document['category_id'] : $document['category_id'];
        }

        sendResponse(201, $response, 'Post créé avec succès');
    }

    // READ - Lire un ou plusieurs posts
    if ($method === 'GET') {
        // GET /posts/latest?limit=5 - Récupérer les derniers posts
        if ($id === 'latest') {
            // Récupérer le paramètre limit depuis la query string
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
            
            // S'assurer que la limite est raisonnable
            if ($limit < 1) $limit = 5;
            if ($limit > 100) $limit = 100;
            
            try {
                $cursor = $posts->find(
                    [],
                    [
                        'sort' => ['date' => -1], // Tri décroissant par date
                        'limit' => $limit
                    ]
                );
                
                $results = [];
                foreach ($cursor as $doc) {
                    $doc['_id'] = (string) $doc['_id'];
                    
                    if (isset($doc['user_id'])) {
                        $doc['user_id'] = is_object($doc['user_id']) ? (string) $doc['user_id'] : $doc['user_id'];
                    }
                    if (isset($doc['category_id'])) {
                        $doc['category_id'] = is_object($doc['category_id']) ? (string) $doc['category_id'] : $doc['category_id'];
                    }
                    if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                        $doc['date'] = $fromUTCDateTime($doc['date']);
                    }
                    
                    $results[] = $doc;
                }
                
                sendResponse(200, $results, "Les $limit derniers posts");
            } catch (Exception $e) {
                sendError(500, 'Erreur lors de la récupération des posts: ' . $e->getMessage());
            }
        }
        // GET /posts/after?date=YYYY-MM-DD - Récupérer les posts après une date
        elseif ($id === 'after' && isset($_GET['date'])) {
            $dateStr = $_GET['date'];
            
            try {
                // Valider et convertir la date
                $date = DateTime::createFromFormat('Y-m-d', $dateStr);
                if (!$date) {
                    sendError(400, 'Format de date invalide. Utilisez YYYY-MM-DD');
                }
                
                // Début de la journée en UTC
                $date->setTime(0, 0, 0);
                $utcDate = new UTCDateTime($date->getTimestamp() * 1000);
                
                $cursor = $posts->find(
                    ['date' => ['$gt' => $utcDate]],
                    ['sort' => ['date' => -1]]
                );
                
                $results = [];
                foreach ($cursor as $doc) {
                    $doc['_id'] = (string) $doc['_id'];
                    
                    if (isset($doc['user_id'])) {
                        $doc['user_id'] = is_object($doc['user_id']) ? (string) $doc['user_id'] : $doc['user_id'];
                    }
                    if (isset($doc['category_id'])) {
                        $doc['category_id'] = is_object($doc['category_id']) ? (string) $doc['category_id'] : $doc['category_id'];
                    }
                    if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                        $doc['date'] = $fromUTCDateTime($doc['date']);
                    }
                    
                    $results[] = $doc;
                }
                
                sendResponse(200, $results, "Posts après le $dateStr");
            } catch (Exception $e) {
                sendError(500, 'Erreur lors de la récupération des posts: ' . $e->getMessage());
            }
        }
        // GET /posts/before?date=YYYY-MM-DD - Récupérer les posts avant une date
        elseif ($id === 'before' && isset($_GET['date'])) {
            $dateStr = $_GET['date'];
            
            try {
                // Valider et convertir la date
                $date = DateTime::createFromFormat('Y-m-d', $dateStr);
                if (!$date) {
                    sendError(400, 'Format de date invalide. Utilisez YYYY-MM-DD');
                }
                
                // Fin de la journée en UTC
                $date->setTime(23, 59, 59);
                $utcDate = new UTCDateTime($date->getTimestamp() * 1000);
                
                $cursor = $posts->find(
                    ['date' => ['$lt' => $utcDate]],
                    ['sort' => ['date' => -1]]
                );
                
                $results = [];
                foreach ($cursor as $doc) {
                    $doc['_id'] = (string) $doc['_id'];
                    
                    if (isset($doc['user_id'])) {
                        $doc['user_id'] = is_object($doc['user_id']) ? (string) $doc['user_id'] : $doc['user_id'];
                    }
                    if (isset($doc['category_id'])) {
                        $doc['category_id'] = is_object($doc['category_id']) ? (string) $doc['category_id'] : $doc['category_id'];
                    }
                    if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                        $doc['date'] = $fromUTCDateTime($doc['date']);
                    }
                    
                    $results[] = $doc;
                }
                
                sendResponse(200, $results, "Posts avant le $dateStr");
            } catch (Exception $e) {
                sendError(500, 'Erreur lors de la récupération des posts: ' . $e->getMessage());
            }
        }
        elseif ($id) {
            try {
                // Essayer avec un ObjectId si c'est un format valide
                if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                    $objectId = new ObjectId($id);
                    $requested = $posts->findOne(['_id' => $objectId]);
                } else {
                    // Sinon essayer avec un ID numérique
                    $requested = $posts->findOne(['_id' => (int) $id]);
                }
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            if (!$requested) {
                sendError(404, 'Post non trouvé');
            }

            $requested['_id'] = (string) $requested['_id'];
            
            // Convertir les IDs en string
            if (isset($requested['user_id'])) {
                $requested['user_id'] = is_object($requested['user_id']) ? (string) $requested['user_id'] : $requested['user_id'];
            }
            if (isset($requested['category_id'])) {
                $requested['category_id'] = is_object($requested['category_id']) ? (string) $requested['category_id'] : $requested['category_id'];
            }
            
            // Convertir la date
            if (isset($requested['date']) && $requested['date'] instanceof UTCDateTime) {
                $requested['date'] = $fromUTCDateTime($requested['date']);
            }

            sendResponse(200, $requested, 'Post récupéré');
        } else {
            $cursor = $posts->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                
                if (isset($doc['user_id'])) {
                    $doc['user_id'] = is_object($doc['user_id']) ? (string) $doc['user_id'] : $doc['user_id'];
                }
                if (isset($doc['category_id'])) {
                    $doc['category_id'] = is_object($doc['category_id']) ? (string) $doc['category_id'] : $doc['category_id'];
                }
                if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                    $doc['date'] = $fromUTCDateTime($doc['date']);
                }
                
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des posts');
        }
    }

    // UPDATE - Mettre à jour un post
    if ($method === 'PUT') {
        if (!$id) {
            sendError(400, 'ID requis pour la mise à jour');
        }

        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        if (empty($input['content'])) {
            sendError(400, 'Le champ content est requis');
        }

        // Vérifier que le post existe
        try {
            // Essayer avec un ObjectId si c'est un format valide
            if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                $objectId = new ObjectId($id);
                $filter = ['_id' => $objectId];
            } else {
                // Sinon essayer avec un ID numérique
                $filter = ['_id' => (int) $id];
            }
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $existingPost = $posts->findOne($filter);
        if (!$existingPost) {
            sendError(404, 'Post non trouvé');
        }

        // Préparer les données à mettre à jour
        $setData = [
            'content' => $input['content']
        ];

        // Valider user_id si fourni
        if (!empty($input['user_id'])) {
            try {
                if (preg_match('/^[a-f\d]{24}$/i', $input['user_id'])) {
                    $setData['user_id'] = new ObjectId($input['user_id']);
                } else {
                    $setData['user_id'] = (int) $input['user_id'];
                }
            } catch (Exception $e) {
                sendError(400, 'user_id invalide');
            }
        }

        // Valider category_id si fourni
        if (isset($input['category_id'])) {
            if (empty($input['category_id'])) {
                // Permet de supprimer la catégorie en passant null ou ""
                $setData['category_id'] = null;
            } else {
                try {
                    if (preg_match('/^[a-f\d]{24}$/i', $input['category_id'])) {
                        $setData['category_id'] = new ObjectId($input['category_id']);
                    } else {
                        $setData['category_id'] = (int) $input['category_id'];
                    }
                } catch (Exception $e) {
                    sendError(400, 'category_id invalide');
                }
            }
        }

        // Gérer la date si fournie
        if (!empty($input['date'])) {
            $dateUTC = $toUTCDateTime($input['date']);
            if ($dateUTC === null) sendError(400, 'Format de date invalide');
            $setData['date'] = $dateUTC;
        }

        $posts->updateOne($filter, ['$set' => $setData]);

        // Récupérer le post mis à jour
        $updated = $posts->findOne($filter);
        $updated['_id'] = (string) $updated['_id'];
        
        if (isset($updated['user_id'])) {
            $updated['user_id'] = is_object($updated['user_id']) ? (string) $updated['user_id'] : $updated['user_id'];
        }
        if (isset($updated['category_id'])) {
            $updated['category_id'] = is_object($updated['category_id']) ? (string) $updated['category_id'] : $updated['category_id'];
        }
        if (isset($updated['date']) && $updated['date'] instanceof UTCDateTime) {
            $updated['date'] = $fromUTCDateTime($updated['date']);
        }

        sendResponse(200, $updated, 'Post mis à jour avec succès');
    }

    // DELETE - Supprimer un post
    if ($method === 'DELETE') {
        if (!$id) {
            sendError(400, 'ID requis pour la suppression');
        }

        try {
            // Essayer avec un ObjectId si c'est un format valide
            if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                $objectId = new ObjectId($id);
                $filter = ['_id' => $objectId];
            } else {
                // Sinon essayer avec un ID numérique
                $filter = ['_id' => (int) $id];
            }
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $result = $posts->deleteOne($filter);

        if ($result->getDeletedCount() > 0) {
            sendResponse(200, null, 'Post supprimé avec succès');
        } else {
            sendError(404, 'Post non trouvé');
        }
    }

    // GET /posts/search?query={mot} - Récupérer les posts contenant un mot clé
    if ($method === 'GET' && isset($segments[2]) && $segments[2] === 'search') {
        $posts = $client->social_network->posts;

        // Récupérer le paramètre query
        $query = $_GET['query'] ?? null;

        if (!$query || trim($query) === '') {
            sendError(400, 'Le paramètre query est requis');
        }

        try {
            // Créer un filtre pour rechercher dans le titre et le contenu
            $filter = [
                '$or' => [
                    ['title' => ['$regex' => $query, '$options' => 'i']],
                    ['content' => ['$regex' => $query, '$options' => 'i']]
                ]
            ];

            $cursor = $posts->find($filter);
            $results = [];

            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $results[] = $doc;
            }

            sendResponse(200, $results, 'Posts trouvés');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors de la recherche: ' . $e->getMessage());
        }
    }

}
