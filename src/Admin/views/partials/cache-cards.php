<?php
    if (!defined("ABSPATH")) {
        exit;
    }
    
    // Page Cache Card
    include_once "cards/page-cache.php";
    
    // Redis Object Cache Card
    include_once "cards/redis-cache.php";
    
    // Cloudflare Cache Card
    include_once "cards/cloudflare-cache.php";
    
    // APO Card
    if ($cloudflare_status["status"] === "active") {
        include_once "cards/cloudflare-apo.php";
    }
?>