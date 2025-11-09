<?php

use MongoDB\BSON\ObjectId;

function user_routes($client): void
{
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/users
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'users') {
        // Ce fichier ne gère que les routes commençant par /api/users
        sendError(404, 'Route utilisateur non trouvée');
    }

    // ID attendu après /api/users/{id}
    $id = $segments[2] ?? null;

    // Get user collection
    $users = $client->social_network->users;

    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);

    // CREATE - Créer un utilisateur
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['username', 'password', 'email'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            sendError(400, 'Email invalide');
        }

        if ($users->findOne(['username' => $input['username']])) {
            sendError(409, 'Nom d\'utilisateur déjà pris');
        }

        // Hasher le mot de passe avant insertion
        $input['password'] = password_hash($input['password'], PASSWORD_DEFAULT);

        $result = $users->insertOne($input);
        $input['_id'] = $result->getInsertedId()->jsonSerialize()['$oid'];

        sendResponse(201, $input, 'Utilisateur créé avec succès');
    }

    // READ - Lire un ou plusieurs utilisateurs
    if ($method === 'GET') {
        // Endpoint spécial: /api/users/count -> retourner le nombre d'utilisateurs
        if ($id === 'count') {
            $total = $users->countDocuments([]);
            sendResponse(200, ['count' => $total], 'Nombre d\'utilisateurs');
        }

        // Endpoint spécial: /api/users/most-followed -> retourner les utilisateurs les plus suivis
        if ($id === 'most-followed') {
            // Récupérer le paramètre limit depuis la query string (par défaut 3)
            $queryParams = [];
            parse_str(parse_url($route, PHP_URL_QUERY) ?? '', $queryParams);
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 3;

            // Agrégation sur la collection follows pour compter les followers
            $follows = $client->social_network->follows;

            $pipeline = [
                // Grouper par user_follow_id (l'utilisateur suivi) et compter
                [
                    '$group' => [
                        '_id' => '$user_follow_id',
                        'followerCount' => ['$sum' => 1]
                    ]
                ],
                // Trier par nombre de followers décroissant
                [
                    '$sort' => ['followerCount' => -1]
                ],
                // Limiter au nombre demandé
                [
                    '$limit' => $limit
                ],
                // Joindre avec la collection users pour récupérer les informations complètes
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => '_id',
                        'foreignField' => '_id',
                        'as' => 'userInfo'
                    ]
                ],
                // Dérouler le tableau userInfo
                [
                    '$unwind' => '$userInfo'
                ],
                // Projeter les champs nécessaires
                [
                    '$replaceRoot' => [
                        'newRoot' => [
                            '$mergeObjects' => [
                                '$userInfo',
                                ['followerCount' => '$followerCount']
                            ]
                        ]
                    ]
                ]
            ];

            $cursor = $follows->aggregate($pipeline);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                unset($doc['password']);
                $results[] = $doc;
            }

            sendResponse(200, $results, 'Utilisateurs les plus suivis');
        }

        if ($id) {
            try {
                $objectId = new ObjectId($id);
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            $requestedUser = $users->findOne(['_id' => $objectId]);

            if (!$requestedUser) {
                sendError(404, 'Utilisateur non trouvé');
            }

            $requestedUser['_id'] = (string) $requestedUser['_id'];
            unset($requestedUser['password']);

            sendResponse(200, $requestedUser, 'Utilisateur récupéré');
        } else {
            $cursor = $users->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                unset($doc['password']);
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des utilisateurs');
        }
    }

    // UPDATE - Mettre à jour un utilisateur
    if ($method === 'PUT') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['username', 'email'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            sendError(400, 'Email invalide');
        }

        try {
            $objectId = new ObjectId($id);
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $user = $users->findOne(['_id' => $objectId]);
        if (!$user) {
            sendError(404, 'Utilisateur non trouvé');
        }

        if ($input['password']) {
            $input['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $users->updateOne(
            ['_id' => $objectId],
            ['$set' => $input]
        );

        sendResponse(200, $user, 'Utilisateur mis à jour avec succès');
    }

    // DELETE - Supprimer un utilisateur
    if ($method === 'DELETE') {
        if (!$id) {
            sendError(400, 'ID requis pour la suppression');
        }
        try {
            $objectId = new ObjectId($id);
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $result = $users->deleteOne(['_id' => $objectId]);

        if ($result->getDeletedCount() > 0) {
            sendResponse(200, null, 'Utilisateur supprimé avec succès');
        } else {
            sendError(404, 'Utilisateur non trouvé');
        }
    }

    // GET /users/pseudos?page={page}&limit=3 - Récupérer les pseudos d'utilisateurs avec pagination
    if ($method === 'GET' && isset($segments[2]) && $segments[2] === 'pseudos') {
        $users = $client->social_network->users;

        // Récupérer les paramètres de pagination
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 3);

        // Valider les paramètres
        if ($page < 1) {
            sendError(400, 'Le paramètre page doit être >= 1');
        }
        if ($limit < 1 || $limit > 100) {
            sendError(400, 'Le paramètre limit doit être entre 1 et 100');
        }

        try {
            // Calculer le skip
            $skip = ($page - 1) * $limit;

            // Compter le nombre total d'utilisateurs
            $total = $users->countDocuments([]);

            // Récupérer les pseudos avec pagination
            $cursor = $users->find([], [
                'projection' => ['pseudo' => 1],
                'skip' => $skip,
                'limit' => $limit
            ]);

            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $results[] = $doc;
            }

            // Préparer la réponse avec les métadonnées de pagination
            $response = [
                'data' => $results,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int) ceil($total / $limit)
                ]
            ];

            sendResponse(200, $response, 'Pseudos récupérés');
        } catch (Exception $e) {
            sendError(500, 'Erreur lors de la récupération des pseudos: ' . $e->getMessage());
        }
    }

}