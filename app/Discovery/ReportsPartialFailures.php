<?php

namespace App\Discovery;

/**
 * A driver that can fail in part: the candidates it found still count, and
 * each failure it kept is recorded on the discovery run.
 */
interface ReportsPartialFailures
{
    /**
     * One line per failure of the last discover() call; empty when none.
     *
     * @return list<string>
     */
    public function failures(): array;
}
