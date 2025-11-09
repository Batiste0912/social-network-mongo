<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function categories_routes($client): void
{
    // Récupérer l'URI et ne garder que le path (sans query string)
    $route = $_SERVER['REQUEST_URI'];
    $path = parse_url($route, PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Découper en segments et s'assurer que la route commence bien par /api/categories
    $segments = explode('/', trim($path, '/'));
    if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'categories') {
        sendError(404, 'Route categories non trouvée');
    }

    // ID attendu après /api/categories/{id}
    $id = $segments[2] ?? null;

    // Get categories collection
    $categories = $client->social_network->categories;

    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);

    // CREATE - Créer une catégorie
    if ($method === 'POST') {
        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        $required = ['name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendError(400, "Le champ {$field} est requis");
            }
        }

        // Vérifier si la catégorie existe déjà
        $existingCategory = $categories->findOne(['name' => $input['name']]);
        if ($existingCategory) {
            sendError(409, 'Une catégorie avec ce nom existe déjà');
        }

        // Préparer le document
        $document = [
            'name' => $input['name'],
        ];

        $result = $categories->insertOne($document);

        // Préparer la réponse
        $response = [
            '_id' => (string) $result->getInsertedId(),
            'name' => $document['name'],
        ];

        sendResponse(201, $response, 'Catégorie créée avec succès');
    }

    // READ - Lire une ou plusieurs catégories
    if ($method === 'GET') {
        if ($id) {
            try {
                // Essayer avec un ObjectId si c'est un format valide
                if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                    $objectId = new ObjectId($id);
                    $requested = $categories->findOne(['_id' => $objectId]);
                } else {
                    // Sinon essayer avec un ID numérique
                    $requested = $categories->findOne(['_id' => (int) $id]);
                }
            } catch (Exception $e) {
                sendError(400, 'ID invalide');
            }

            if (!$requested) {
                sendError(404, 'Catégorie non trouvée');
            }

            $requested['_id'] = (string) $requested['_id'];

            sendResponse(200, $requested, 'Catégorie récupérée');
        } else {
            $cursor = $categories->find([]);
            $results = [];
            foreach ($cursor as $doc) {
                $doc['_id'] = (string) $doc['_id'];
                $results[] = $doc;
            }
            sendResponse(200, $results, 'Liste des catégories');
        }
    }

    // UPDATE - Mettre à jour une catégorie
    if ($method === 'PUT') {
        if (!$id) {
            sendError(400, 'ID requis pour la mise à jour');
        }

        if (!is_array($input)) {
            sendError(400, 'Données JSON invalides');
        }

        if (empty($input['name'])) {
            sendError(400, 'Le champ name est requis');
        }

        // Vérifier que la catégorie existe
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

        $existingCategory = $categories->findOne($filter);
        if (!$existingCategory) {
            sendError(404, 'Catégorie non trouvée');
        }

        // Vérifier si le nouveau nom existe déjà (et n'est pas la catégorie actuelle)
        $nameExists = $categories->findOne([
            'name' => $input['name'],
            '_id' => ['$ne' => $filter['_id']]
        ]);

        if ($nameExists) {
            sendError(409, 'Une catégorie avec ce nom existe déjà');
        }

        // Préparer les données à mettre à jour
        $setData = [
            'name' => $input['name']
        ];

        $categories->updateOne($filter, ['$set' => $setData]);

        // Récupérer la catégorie mise à jour
        $updated = $categories->findOne($filter);
        $updated['_id'] = (string) $updated['_id'];

        sendResponse(200, $updated, 'Catégorie mise à jour avec succès');
    }

    // DELETE - Supprimer une catégorie
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

        $result = $categories->deleteOne($filter);

        if ($result->getDeletedCount() > 0) {
            sendResponse(200, null, 'Catégorie supprimée avec succès');
        } else {
            sendError(404, 'Catégorie non trouvée');
        }
    }
}

