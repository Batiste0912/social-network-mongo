<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function comments_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/comments
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'comments') {
        // Ce fichier ne gère que les routes commençant par /api/comments
        sendError(404, 'Route comments non trouvée');
    }

    // ID attendu après /api/comments/{id}
    $id = $segments[2] ?? null;

    // Get comments collection
    $comments = $client->social_network->comments;

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

    // CREATE - Créer un commentaire
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['content', 'post_id', 'user_id'];
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

        // Ajouter la date si elle n'existe pas
        if (empty($input['date'])) {
            $utcNow = new UTCDateTime((int)(microtime(true) * 1000));
        } else {
            $utcNow = $toUTCDateTime($input['date']);
            if ($utcNow === null) sendError(400, 'Format de date invalide');
        }

        // Normaliser les ids stockés en tant qu'ObjectId
        $document = [
            'content' => $input['content'],
            'post_id' => $postObjectId,
            'user_id' => $userObjectId,
            'date' => $utcNow,
        ];

        $result = $comments->insertOne($document);

        // Préparer la réponse en convertissant les ids et la date
        $response = [
            '_id' => (string) $result->getInsertedId(),
            'content' => $document['content'],
            'post_id' => (string) $document['post_id'],
            'user_id' => (string) $document['user_id'],
            'date' => $fromUTCDateTime($document['date']),
        ];

        sendResponse(201, $response, 'Commentaire créé avec succès');
    }

    // READ - Lire un ou plusieurs commentaires
    if ($method === 'GET') {
        if ($id) {
            try {
                $objectId = new ObjectId($id);
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            $requested = $comments->findOne(['_id' => $objectId]);

            if (!$requested) {
                sendError(404, 'Commentaire non trouvé');
            }

            $requested['_id'] = (string) $requested['_id'];
            // Convertir les ObjectId de post_id et user_id en string si présents
            if (isset($requested['post_id'])) {
                $requested['post_id'] = (string) $requested['post_id'];
            }
            if (isset($requested['user_id'])) {
                $requested['user_id'] = (string) $requested['user_id'];
            }
            // Convertir la date si c'est un UTCDateTime
            if (isset($requested['date']) && $requested['date'] instanceof UTCDateTime) {
                $requested['date'] = $fromUTCDateTime($requested['date']);
            }

            sendResponse(200, $requested, 'Commentaire récupéré');
        } else {
            $cursor = $comments->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                if (isset($doc['post_id'])) {
                    $doc['post_id'] = (string) $doc['post_id'];
                }
                if (isset($doc['user_id'])) {
                    $doc['user_id'] = (string) $doc['user_id'];
                }
                if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                    $doc['date'] = $fromUTCDateTime($doc['date']);
                }
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des commentaires');
        }
    }

    // UPDATE - Mettre à jour un commentaire
    if ($method === 'PUT') {
        if (!$id) {
            sendError(400, 'ID requis pour la mise à jour');
        }

        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['content'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        // Valider les ObjectId pour post_id et user_id si fournis
        $postObjectId = null;
        $userObjectId = null;
        if (!empty($input['post_id'])) {
            try {
                $postObjectId = new ObjectId($input['post_id']);
            } catch (Exception $e) {
                sendError(400, 'post_id invalide');
            }
        }
        if (!empty($input['user_id'])) {
            try {
                $userObjectId = new ObjectId($input['user_id']);
            } catch (Exception $e) {
                sendError(400, 'user_id invalide');
            }
        }

        // Gérer la date si fournie
        $dateUTC = null;
        if (!empty($input['date'])) {
            $dateUTC = $toUTCDateTime($input['date']);
            if ($dateUTC === null) sendError(400, 'Format de date invalide');
        }

        // Vérifier que le commentaire existe
        try {
            $objectId = new ObjectId($id);
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $existingComment = $comments->findOne(['_id' => $objectId]);
        if (!$existingComment) {
            sendError(404, 'Commentaire non trouvé');
        }

        // Préparer les données à mettre à jour
        $setData = [];
        $setData['content'] = $input['content'];
        if ($postObjectId) $setData['post_id'] = $postObjectId;
        if ($userObjectId) $setData['user_id'] = $userObjectId;
        if ($dateUTC) $setData['date'] = $dateUTC;

        $comments->updateOne(['_id' => $objectId], ['$set' => $setData]);

        // Préparer la réponse (normaliser les types pour l'output)
        $output = $setData;
        $output['_id'] = $id;
        if (isset($output['post_id'])) $output['post_id'] = (string) $output['post_id'];
        if (isset($output['user_id'])) $output['user_id'] = (string) $output['user_id'];
        if (isset($output['date']) && $output['date'] instanceof UTCDateTime) {
            $output['date'] = $fromUTCDateTime($output['date']);
        }

        sendResponse(200, $output, 'Commentaire mis à jour avec succès');
    }

    // DELETE - Supprimer un commentaire
    if ($method === 'DELETE') {
        if (!$id) {
            sendError(400, 'ID requis pour la suppression');
        }
        try {
            $objectId = new ObjectId($id);
        } catch (Exception $e) {
            sendError(400, 'ID invalide');
        }

        $result = $comments->deleteOne(['_id' => $objectId]);

        if ($result->getDeletedCount() > 0) {
            sendResponse(200, null, 'Commentaire supprimé avec succès');
        } else {
            sendError(404, 'Commentaire non trouvé');
        }
    }
}
