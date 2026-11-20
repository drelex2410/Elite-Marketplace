<?php

namespace App\Addons\Multivendor;

use App\LaravelAddons\Addon;

class MultivendorAddon extends Addon
{
    public $name = 'Multivendor';

    public function boot()
    {
        // Controllers use the compatibility namespace while views live in the
        // application's normal resources/views/seller directory.
    }
}
