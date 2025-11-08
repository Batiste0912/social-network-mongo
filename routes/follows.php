<?php

use MongoDB\BSON\ObjectId;

function follows_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/follows
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'follows') {
        // Ce fichier ne gère que les routes commençant par /api/follows
        sendError(404, 'Route follows non trouvée');
    }

    // ID ou action attendue après /api/follows/{id_or_action}
    $param = $segments[2] ?? null;

    // Get follows collection
    $follows = $client->social_network->follows;

    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);

    // CREATE - Créer un follow (user_id suit user_follow_id)
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['user_id', 'user_follow_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        // Valider les ObjectId pour user_id et user_follow_id
        try {
            $userObjectId = new ObjectId($input['user_id']);
            $userFollowObjectId = new ObjectId($input['user_follow_id']);
        } catch (Exception $e) {
            sendError(400, 'user_id ou user_follow_id invalide');
        }

        // Vérifier qu'un utilisateur ne peut pas se suivre lui-même
        if ($input['user_id'] === $input['user_follow_id']) {
            sendError(400, 'Un utilisateur ne peut pas se suivre lui-même');
        }

        // Vérifier qu'une relation similaire n'existe pas déjà
        $existingFollow = $follows->findOne([
            'user_id' => $userObjectId,
            'user_follow_id' => $userFollowObjectId
        ]);

        if ($existingFollow) {
            sendError(409, 'Cette relation de suivi existe déjà');
        }

        // Créer le document
        $document = [
            'user_id' => $userObjectId,
            'user_follow_id' => $userFollowObjectId,
        ];

        $result = $follows->insertOne($document);

        // Préparer la réponse en convertissant les ids
        $response = [
            '_id' => (string) $result->getInsertedId(),
            'user_id' => (string) $document['user_id'],
            'user_follow_id' => (string) $document['user_follow_id'],
        ];

        sendResponse(201, $response, 'Follow créé avec succès');
    }

    // READ - Lire les follows
    if ($method === 'GET') {
        // /api/follows/following/{user_id} -> Liste des users suivis par user_id
        if ($param === 'following' && isset($segments[3])) {
            $userId = $segments[3];

            try {
                $userObjectId = new ObjectId($userId);
            } catch (Exception $e) {
                sendError(400, 'user_id invalide');
            }

            $cursor = $follows->find(['user_id' => $userObjectId]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $doc['user_id'] = (string) $doc['user_id'];
                $doc['user_follow_id'] = (string) $doc['user_follow_id'];
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des utilisateurs suivis');
        }

        // /api/follows/followers/{user_id} -> Liste des users qui suivent user_id
        if ($param === 'followers' && isset($segments[3])) {
            $userId = $segments[3];

            try {
                $userObjectId = new ObjectId($userId);
            } catch (Exception $e) {
                sendError(400, 'user_id invalide');
            }

            $cursor = $follows->find(['user_follow_id' => $userObjectId]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $doc['user_id'] = (string) $doc['user_id'];
                $doc['user_follow_id'] = (string) $doc['user_follow_id'];
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des followers');
        }

        // /api/follows/{id} -> Récupérer un follow spécifique par son ID
        if ($param && $param !== 'following' && $param !== 'followers') {
            try {
                $objectId = new ObjectId($param);
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            $requested = $follows->findOne(['_id' => $objectId]);

            if (!$requested) {
                sendError(404, 'Follow non trouvé');
            }

            $requested['_id'] = (string) $requested['_id'];
            $requested['user_id'] = (string) $requested['user_id'];
            $requested['user_follow_id'] = (string) $requested['user_follow_id'];

            sendResponse(200, $requested, 'Follow récupéré');
        }

        // /api/follows -> Liste de tous les follows
        if (!$param) {
            $cursor = $follows->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $doc['user_id'] = (string) $doc['user_id'];
                $doc['user_follow_id'] = (string) $doc['user_follow_id'];
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste de tous les follows');
        }
    }

    // DELETE - Supprimer un follow
    if ($method === 'DELETE') {
        // Deux possibilités: DELETE par ID ou DELETE par user_id + user_follow_id

        // Si on a un ID dans l'URL: /api/follows/{id}
        if ($param) {
            try {
                $objectId = new ObjectId($param);
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            $result = $follows->deleteOne(['_id' => $objectId]);

            if ($result->getDeletedCount() > 0) {
                sendResponse(200, null, 'Follow supprimé avec succès');
            } else {
                sendError(404, 'Follow non trouvé');
            }
        }
        // Sinon, via le body JSON avec user_id et user_follow_id
        else {
            if (!is_array($input)) {
                sendError(400, 'Données JSON invalides');
            }

            $required = ['user_id', 'user_follow_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    sendError(400, "Le champ {$field} est requis");
                }
            }

            // Valider les ObjectId
            try {
                $userObjectId = new ObjectId($input['user_id']);
                $userFollowObjectId = new ObjectId($input['user_follow_id']);
            } catch (Exception $e) {
                sendError(400, 'user_id ou user_follow_id invalide');
            }

            // Vérifier qu'une relation existe
            $existingFollow = $follows->findOne([
                'user_id' => $userObjectId,
                'user_follow_id' => $userFollowObjectId
            ]);

            if (!$existingFollow) {
                sendError(404, 'Cette relation de suivi n\'existe pas');
            }

            // Supprimer la relation
            $result = $follows->deleteOne([
                'user_id' => $userObjectId,
                'user_follow_id' => $userFollowObjectId
            ]);

            if ($result->getDeletedCount() > 0) {
                sendResponse(200, null, 'Follow supprimé avec succès');
            } else {
                sendError(404, 'Follow non trouvé');
            }
        }
    }
}

