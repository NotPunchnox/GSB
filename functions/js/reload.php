<?php

    function reload($name, $page) {
        echo "<script>window.history.pushState('" . $name . "', 'Title', '" . $page . "'); window.location.reload(); </script>";
    }

?>