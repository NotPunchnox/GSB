<?php
session_start();

// Inclure les fonctions
include("../../functions/index.php");

// Reset le cookie
deleteCookie("GSB");
deleteCookie("PHPSESSID");

// Reset la session
$_SESSION['nom'] = "";
$_SESSION['id'] = "";
$_SESSION['mdp'] = "";
$_SESSION['login'] = "";

header("Location: " . $GLOBALS['baseURL'] . "");

?>