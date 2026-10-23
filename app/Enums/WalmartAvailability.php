<?php

namespace App\Enums;

/**
 * What the last signed-in cart import said about a matched product.
 */
enum WalmartAvailability: string
{
    case Ok = 'ok';
    case OutOfStock = 'out-of-stock';

    /** Carted, but only as a marketplace ship item, never pickup. */
    case ShipOnly = 'ship-only';
}
