<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A recipe page or feed the site would not give the app: a non-200, a
 * bot-wall challenge or a timeout. The message says which, with the address.
 */
class RecipePageNotFetched extends RuntimeException {}
