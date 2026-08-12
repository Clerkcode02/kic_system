<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Marker interface for single-purpose Action classes.
 *
 * Implementations expose exactly one public method, `handle()`, containing
 * the business logic for a single write operation or command. Parameter and
 * return types are specific to each Action, so they are not declared here.
 */
interface Action
{
}
