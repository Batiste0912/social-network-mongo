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
}