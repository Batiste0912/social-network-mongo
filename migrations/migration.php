<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Faker\Factory as Faker;

class CreateSocialNetworkMigration
{
    private $client;
    private $db;
    private $faker;

    public function __construct()
    {
        $this->client = new Client("mongodb+srv://db_user:1234567890@next-u-cluster.hc5o3l4.mongodb.net/");
        $this->db = $this->client->social_network;
        $this->faker = Faker::create('fr_FR');
    }

    public function up()
    {
        echo "🚀 Création de la base 'social_network'...\n";

        // --- User ---
        $users = [];
        for ($i = 1; $i <= 100; $i++) {
            $users[] = [
                "_id" => $i,
                "username" => $this->faker->name(),
                "email" => $this->faker->unique()->safeEmail(),
                "password" => password_hash($this->faker->password, PASSWORD_DEFAULT),
                "is_active" => $this->faker->boolean(75)
            ];
        }
        $this->db->users->insertMany($users);

        // --- Posts ---
        $posts = [];
        for ($i = 1; $i <= 1000; $i++) {
            $posts[] = [
                "_id" => $i,
                "content" => $this->faker->text(200),
                "category_id" => $this->faker->numberBetween(1, 10),
                "user_id" => $this->faker->numberBetween(1, 50),
                "date" => new UTCDateTime($this->faker->dateTimeBetween("-1 years", "now")->getTimestamp() * 1000),
            ];
        }
        $this->db->posts->insertMany($posts);

        // --- Categories ---
        $categories = [];
        for ($i = 501; $i <= 510; $i++) {
            $categories[] = [
                "_id" => $i,
                "name" => $this->faker->word(),
            ];
        }
        $this->db->categories->insertMany($categories);

        // --- Comments ---
        $comments = [];
        for ($i = 1; $i <= 1000; $i++) {
            $comments[] = [
                "_id" => $i,
                "content" => $this->faker->text(100),
                "user_id" => $this->faker->numberBetween(1, 50),
                "post_id" => $this->faker->numberBetween(1, 500),
                "date" => new UTCDateTime($this->faker->dateTimeBetween("-3 years", "now")->getTimestamp() * 1000),
            ];
        }
        $this->db->comments->insertMany($comments);

        // --- Likes ---
        $likes = [];
        for ($i = 1; $i <= 1000; $i++) {
            $likes[] = [
                "_id" => $i,
                "post_id" => $this->faker->numberBetween(1, 500),
                "user_id" => $this->faker->numberBetween(1, 50),
            ];
        }
        $this->db->likes->insertMany($likes);

        // --- Follows ---
        $follows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $follows[] = [
                "_id" => $i,
                "user_id" => $this->faker->numberBetween(1, 50),
                "user_follow_id" => $this->faker->numberBetween(1, 50),
            ];
        }

        echo "✅ Base 'bibliotheque' créée avec succès.\n";
    }

    public function down()
    {
        $this->client->dropDatabase('bibliotheque');
        echo "🗑️ Base 'bibliotheque' supprimée.\n";
    }
}

// --- EXÉCUTION ---
$migration = new CreateSocialNetworkMigration();
$migration->up();
