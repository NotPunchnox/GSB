<?php

function createCookie($name, $value, $days = 30) {
    // Calculer la durée de vie en secondes
    $expire = time() + (60 * 60 * 24 * $days);

    // Créer le cookie
    setcookie($name, $value, $expire, "/");
}

?>
