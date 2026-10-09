<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A recipe write supplied the recipe shape with a part missing; the message is safe to show the household.
 */
class RecipeShapeRefused extends RuntimeException {}
