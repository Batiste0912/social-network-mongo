<?php

use MongoDB\Client;

class PostsRoutes {
    private $collection;

    public function __construct() {
        $client = new Client("mongodb+srv://db_user:1234567890@next-u-cluster.hc5o3l4.mongodb.net/");
        $this->collection = $client->socialNetwork->posts;
    }

    public function getAllPosts() {
        try {
            $posts = $this->collection->find()->toArray();
            return json_encode(['status' => 'success', 'data' => $posts]);
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getPost($id) {
        try {
            $post = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return json_encode(['status' => 'success', 'data' => $post]);
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function createPost($data) {
        try {
            $result = $this->collection->insertOne($data);
            return json_encode(['status' => 'success', 'id' => (string)$result->getInsertedId()]);
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function updatePost($id, $data) {
        try {
            $result = $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $data]
            );
            return json_encode(['status' => 'success', 'modified' => $result->getModifiedCount()]);
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function deletePost($id) {
        try {
            $result = $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return json_encode(['status' => 'success', 'deleted' => $result->getDeletedCount()]);
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}