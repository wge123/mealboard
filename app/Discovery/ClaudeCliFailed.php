<?php

namespace App\Discovery;

use RuntimeException;

/**
 * The local claude CLI could not answer: it is missing, or it exited with a
 * failure. The boundary callers may show or record; the message is safe to
 * show the household.
 */
class ClaudeCliFailed extends RuntimeException {}
