<?php

// Création d'une fonction permettant d'avertir l'utilisateur avec une alerte
function alert(string $message): void {
    echo "<script>alert('" . $message . "')</script>";
}

?>