<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The tool inventory refused a change; the message is safe to show the household.
 */
class KitchenToolRefused extends RuntimeException {}
