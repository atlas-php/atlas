<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Illuminate\Support\Facades\App;

if (! function_exists('atlas')) {
    /**
     * Get the Atlas manager instance.
     */
    function atlas(): AtlasManager
    {
        return App::make(AtlasManager::class);
    }
}
