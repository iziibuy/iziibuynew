<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Not bound anywhere in this Laravel 11+/13 app — command discovery and
 * scheduling are wired via bootstrap/app.php (see ->withSchedule()), which
 * is what actually runs. This class is kept only in case something in a
 * package or deploy script still type-hints Illuminate\Contracts\Console\Kernel.
 *
 * @see \bootstrap/app.php
 */
class Kernel extends ConsoleKernel
{
    //
}
