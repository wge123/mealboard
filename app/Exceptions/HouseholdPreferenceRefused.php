<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The household preferences refused a change; the message is safe to show the household.
 */
class HouseholdPreferenceRefused extends RuntimeException {}
