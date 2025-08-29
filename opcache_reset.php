<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache has been reset!";
} else {
    echo "OPcache is not available.";
}