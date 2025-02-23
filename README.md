PROJET AP BTS SIO.

Configuration possible dans: [config.php](./config/config.php)
Port de la base de donnée: 3306

/!\ Si PDO n'est pas installé cela va créer une erreur: `no driver found`.

Pour la corriger: xamp/php/php.ini

et ajouter:
```ini
extension=pdo
extension=pdo_mysql
```