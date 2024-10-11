CREATE TABLE routes_log (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nom_route VARCHAR(512) NOT NULL,
                            count INT DEFAULT 0,
                            last_update DATETIME NOT NULL,
                            UNIQUE KEY unique_route (nom_route)
);
