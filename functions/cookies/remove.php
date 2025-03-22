<?php

// Function pour supprimer un cookie
function deleteCookie($cookieName) {
    if (isset($_COOKIE[$cookieName])) {

        // Rendre le cookie expiré en mettant une date trop veille
        setcookie($cookieName, '', time() - 3600, "/");
        
        // Supression du cookie dans la variable global $_COOKIE pour etre sur que la supression se fasse correctement
        unset($_COOKIE[$cookieName]);
        return true;
    }
    return false;
}
?>