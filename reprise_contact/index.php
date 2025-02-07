<?php
$servername = "db";
$username = "root";
$password = "motdepasse";
$dbname = "ma_base_de_donnees";
$cached = "dans bd";

// Connexion à MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Connexion à Redis
$redis = new Redis();
$redis->connect('redis', 6379);

// Vérifier si les données sont en cache
$cachedData = $redis->get("utilisateurs");
if ($cachedData) {
    $utilisateurs = json_decode($cachedData, true);
    $cached = "dans cache";
} else {
    $sql = "SELECT * FROM utilisateurs";
    $result = $conn->query($sql);

    $utilisateurs = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $utilisateurs[] = $row;
        }
    }

    // Mettre en cache les résultats pendant 60 secondes
    $redis->set("utilisateurs", json_encode($utilisateurs), 60);
}

// Afficher les utilisateurs
echo "<h1>Liste des utilisateurs " . $cached . " </h1><ul>";
foreach ($utilisateurs as $user) {
    echo "<li>" . $user["nom"] . " - " . $user["email"] . "</li>";
}
echo "</ul>";

$conn->close();
?>
