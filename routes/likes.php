<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function likes_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/likes
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'likes') {
        sendError(404, 'Route likes non trouvée');
    }

    // ID attendu après /api/likes/{id}
    $id = $segments[2] ?? null;

    // Get likes collection
    $likes = $client->social_network->likes;

    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);

    // CREATE - Créer un like
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['post_id', 'user_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        // Valider les ObjectId pour post_id et user_id
        try {
            $postObjectId = new ObjectId($input['post_id']);
            $userObjectId = new ObjectId($input['user_id']);
        } catch (Exception $e) {
            sendError(400, 'post_id ou user_id invalide');
        }

        // Vérifier si le like existe déjà
        $existingLike = $likes->findOne([
            'post_id' => $postObjectId,
            'user_id' => $userObjectId
        ]);

        if ($existingLike) {
            sendError(409, 'Ce like existe déjà');
        }

        // Normaliser les ids stockés en tant qu'ObjectId
        $document = [
            'post_id' => $postObjectId,
            'user_id' => $userObjectId,
        ];

        $result = $likes->insertOne($document);

        // Préparer la réponse en convertissant les ids
        $response = [
            '_id' => (string) $result->getInsertedId(),
            'post_id' => (string) $document['post_id'],
            'user_id' => (string) $document['user_id'],
        ];

        sendResponse(201, $response, 'Like créé avec succès');
    }

    // READ - Lire un ou plusieurs likes
    if ($method === 'GET') {
        if ($id) {
            try {
                $objectId = new ObjectId($id);
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            $requested = $likes->findOne(['_id' => $objectId]);

            if (!$requested) {
                sendError(404, 'Like non trouvé');
            }

            $requested['_id'] = (string) $requested['_id'];
            // Convertir les ObjectId de post_id et user_id en string si présents
            if (isset($requested['post_id'])) {
                $requested['post_id'] = (string) $requested['post_id'];
            }
            if (isset($requested['user_id'])) {
                $requested['user_id'] = (string) $requested['user_id'];
            }

            sendResponse(200, $requested, 'Like récupéré');
        } else {
            $cursor = $likes->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                if (isset($doc['post_id'])) {
                    $doc['post_id'] = (string) $doc['post_id'];
                }
                if (isset($doc['user_id'])) {
                    $doc['user_id'] = (string) $doc['user_id'];
                }
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des likes');
        }
    }

    // DELETE - Supprimer un like
    if ($method === 'DELETE') {
        if (!$id) {
            sendError(400, 'ID requis pour la suppression');
        }
        try {
            $objectId = new ObjectId($id);
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $result = $likes->deleteOne(['_id' => $objectId]);

        if ($result->getDeletedCount() > 0) {
            sendResponse(200, null, 'Like supprimé avec succès');
        } else {
            sendError(404, 'Like non trouvé');
        }
    }
}
