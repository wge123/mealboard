<?php

namespace App\Discovery;

use RuntimeException;

/**
 * A recipe site's feed came back but is not an RSS feed the driver can read.
 */
class FeedNotReadable extends RuntimeException {}
