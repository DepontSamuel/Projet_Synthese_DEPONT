# Mini Projet de synthèse

## Samuel Depont

## 1. Objectif du projet
Ce projet consiste à déployer un environnement composé de plusieurs services Docker dans un cluster Swarm. L'architecture comprend :

- **Traefik** en tant que reverse proxy et load balancer.
- **MySQL** pour la base de données.
- **Redis** pour la mise en cache.
- **PHP + Apache** pour le serveur web.

## 2. Configuration des services

### 2.1. Traefik
Traefik est configuré pour gérer le routage des requêtes HTTP vers les services appropriés. Il est exposé sur les ports 80 et 8080.

```yaml
docker-compose.yml
version: "3.8"

services:
  traefik:
    image: "traefik:v2.9"
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
      # - "--providers.docker.swarmMode=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entryPoints.web.address=:80"
    ports:
      - "80:80" 
      - "8080:8080" 
    networks:
      - mon_reseau_tp
    volumes:
      - "/var/run/docker.sock:/var/run/docker.sock:ro" 
    deploy:
      placement:
        constraints:
          - node.role == manager
```

### 2.2. Base de données MySQL
Un service MySQL est mis en place avec un volume persistant pour stocker les données.

```yaml
db:
  image: mysql:latest
  environment:
    MYSQL_ROOT_PASSWORD: motdepasse
    MYSQL_DATABASE: ma_base_de_donnees
  networks:
    - mon_reseau_tp
  volumes:
    - db_data_tp:/var/lib/mysql
  deploy:
    placement:
      constraints:
        - node.hostname == o22203701-75
```

### 2.3. Service Web (PHP + Apache)
Le service web utilise une image personnalisée basée sur PHP 7.4 avec Apache, MySQL et Redis activés.

```dockerfile
FROM php:7.4-apache

RUN docker-php-ext-install mysqli
RUN pecl install redis && docker-php-ext-enable redis

EXPOSE 80
```

Configuration du service web :

```yaml
reprise_contact:
  image: reprise_contact
  build: ./reprise_contact/.
  networks:
    - mon_reseau_tp
  depends_on:
    - db
    - redis
    - traefik
  volumes:
    - /home/o22203701/TP_Note/reprise_contact:/var/www/html
  deploy:
    placement:
      constraints:
        - node.hostname == o22203701-218
  labels:
    - "traefik.enable=true"
    - "traefik.http.routers.web_apache.rule=Host(`utilisateurs.o22203701-75`)"
    - "traefik.http.routers.web_apache.entrypoints=web"
    - "traefik.http.services.web_apache.loadbalancer.server.port=80"
```

### 2.4. Service Redis
Un service Redis est utilisé pour stocker temporairement les données et améliorer les performances.

```yaml
redis:
  image: redis:latest
  networks:
    - mon_reseau_tp
  ports:
    - "6379:6379"
  deploy:
    placement:
      constraints:
        - node.hostname == o22203701-89
```

### 2.5. Réseau Docker et Volumes

```yaml
# Définition du réseau Swarm
networks:
  mon_reseau_tp:
    driver: overlay

# Définition des volumes
volumes:
  db_data_tp:
```

## 3. Fonctionnement du Code PHP
Le script PHP suivant se connecte à MySQL et Redis pour récupérer et afficher les utilisateurs.

```php
<?php
$servername = "db";
$username = "root";
$password = "motdepasse";
$dbname = "ma_base_de_donnees";
$cached = "dans bd";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$redis = new Redis();
$redis->connect('redis', 6379);

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
    $redis->set("utilisateurs", json_encode($utilisateurs), 60);
}

echo "<h1>Liste des utilisateurs " . $cached . " </h1><ul>";
foreach ($utilisateurs as $user) {
    echo "<li>" . $user["nom"] . " - " . $user["email"] . "</li>";
}
echo "</ul>";

$conn->close();
?>
```

## 4. Problèmes rencontrés et solutions

### 4.1. Problème de quota de téléchargement des images Docker
**Problème** : Impossible de télécharger certaines images Docker en raison d'un quota atteint.
**Solution** : J'ai contacté M. Melin qui m'a fourni un registre privé accessible avec la commande suivante :

```bash
$ curl p4179-167:5000/v2/_catalog
```

Réponse :

```json
{"repositories":["alpine","bash","basic_web_flask","busybox","gcr.io/distroless/cc","gcr.io/distroless/java17-debian12","hello-world","itsthenetwork/nfs-server-alpine","mon-app","myphp","mysql","nginx","nginxdemos/hello","openjdk","php","python","redis","redis-stack","rust","stress","traefik","ubuntu"]}
```

## 4.2. Parties non réalisées

Je n'ai pas eu le temps de réaliser les parties suivantes :

Site web "Cluster Swarm"

Application "Fortune Images"

Site web Flask permettant de populer une base de données Redis.

## 5. Lancement du projet

Avant de lancer le projet, il est nécessaire de récupérer les images Docker :

```bash
docker pull reprise_contact
docker pull traefik:v2.9
docker pull mysql:latest
docker pull redis:latest
```

Ensuite, construire l'image :

```bash
docker build -t reprise_contact ./
```

Déployer le projet avec Docker Swarm :

```bash
docker stack deploy -c ./docker-compose.yml reprise_contact
```

Vérifier le déploiement :

```bash
docker stack ps reprise_contact
```
ou
```bash
watch docker stack ps reprise_contact
```

Tester le service :

```bash
curl --resolve utilisateurs.o22203701-75:80:127.0.0.1 utilisateurs.o22203701-75
```

## 6. Conclusion
Ce projet met en place une architecture complète avec plusieurs services interconnectés. Malgré quelques problèmes initiaux, les ajustements ont permis d'obtenir un environnement fonctionnel et optimisé pour le cache avec Redis et la gestion des requêtes avec Traefik.

 