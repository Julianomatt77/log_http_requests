<?php
//  php ./count_webservices_calls.php
// Connexion à la base de données
//$pdo = new PDO('mysql:host=localhost;dbname=count_webservices', 'root', 'yolo');
try {
    // Charger le fichier .env
    loadEnv(__DIR__ . '/.env.local');

    // Récupérer les informations de la base de données depuis les variables d'environnement
    $dbUrl = $_ENV['DATABASE_URL'];
    $dbLogin = $_ENV['DATABASE_LOGIN'];
    $dbPwd = $_ENV['DATABASE_PWD'];

    // Créer l'objet PDO
    $pdo = new PDO($dbUrl, $dbLogin, $dbPwd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connexion réussie à la base de données.";
} catch (Exception $e) {
    echo 'Erreur : ' . $e->getMessage();
} catch (PDOException $e) {
    echo 'Échec de la connexion : ' . $e->getMessage();
}

// Chemin du fichier de log Nginx
$logFile = '/var/log/nginx/access.log.1';

// Date et heure actuelles pour la mise à jour de la dernière modification
$now = new DateTime();
$formattedNow = $now->format('Y-m-d H:i:s');

// Tableau pour compter les routes
$routesCount = [];
// Lire le fichier ligne par ligne
$file = fopen($logFile, 'r');
if ($file) {
    while (($line = fgets($file)) !== false) {
        // Capture tout ce qu'il y a avant le "?" ou toute autre partie pertinente
        preg_match('/\"(GET|POST|PUT|DELETE|PATCH) (.*?)(\?|img:[^\/\s]*)/', $line, $matches);

        if (isset($matches[2])) {
            // Vérifier si la route commence par /img, et si c'est le cas, l'ignorer
            if (strpos($matches[2], '/img') !== 0) {
                // Utiliser le 2ème preg_match pour capturer la route jusqu'à ce qu'un segment numérique soit rencontré
                preg_match('/^(\/(?:[^\/]+\/)+?)(?:\d+)?\/?$/', $matches[2], $routeMatches);

                if (isset($routeMatches[1])) {
                    $route = $routeMatches[1]; // Ne garder que la partie avant les chiffres
                } else {
                    $route = $matches[2];
                }

                // Avant d'insérer la route dans le tableau et de compter les occurrences
                if (strlen($route) > 512) {
                    $route = substr($route, 0, 512);
                }

                // Compter les occurrences de la route
                if (!isset($routesCount[$route])) {
                    $routesCount[$route] = 0;
                }
                $routesCount[$route]++;
            }
        }
//        }
    }
    fclose($file);
}

// Insérer ou mettre à jour les données dans la base de données
foreach ($routesCount as $route => $count) {
    // Vérifier si la route existe déjà dans la base
    $stmt = $pdo->prepare('SELECT count FROM routes_log WHERE nom_route = ?');
    $stmt->execute([$route]);
    $existingCount = $stmt->fetchColumn();

    if ($existingCount !== false) {
        // Si la route existe déjà, mettre à jour le compteur et la date de dernière mise à jour
        $newCount = $existingCount + $count;
        $updateStmt = $pdo->prepare('UPDATE routes_log SET count = ?, last_update = ? WHERE nom_route = ?');
        $updateStmt->execute([$newCount, $formattedNow, $route]);
    } else {
        // Sinon, insérer une nouvelle entrée pour la route avec la date de création et de dernière mise à jour
        $insertStmt = $pdo->prepare('INSERT INTO routes_log (nom_route, count, last_update) VALUES (?, ?, ?)');
        $insertStmt->execute([$route, $count, $formattedNow]);
    }
}

echo ("Mise à jour des logs terminée à " . $formattedNow);

function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception("Le fichier .env est introuvable.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorer les lignes de commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Séparer la clé et la valeur
        list($key, $value) = explode('=', $line, 2);

        // Nettoyer les guillemets autour des valeurs (s'ils existent)
        $value = trim($value, '"');

        // Ajouter dans $_ENV
        $_ENV[trim($key)] = $value;
    }
}


