<?php

namespace App\Exceptions;

use Exception;

/**
 * The discovery environment itself is broken: a configured interpreter or
 * binary is missing, unreadable, or not executable.
 *
 * Deliberately NOT a RuntimeException. Per-video extraction failures are
 * RuntimeExceptions, and ExtractRecipeFromVideo records those on the
 * discovered_videos row, which permanently excludes the video from every
 * future run (YouTubeDriver filters on whereNull('error')). That is the right
 * treatment for a captionless video and exactly the wrong treatment for a
 * broken interpreter path: the video is fine, the machine is not, and
 * blacklisting one healthy video per run is a silent, unrecoverable data loss.
 *
 * So this type escapes the per-video catch and fails the whole driver, where
 * RunDiscovery records it and the scheduler's onFailure hook reports it.
 */
class DiscoveryEnvironmentException extends Exception {}
