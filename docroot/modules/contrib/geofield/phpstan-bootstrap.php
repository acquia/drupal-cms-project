<?php

/**
 * @file
 * Loads the geoPHP classes for PHPStan.
 *
 * The Composer class map of itamair/geophp lists only geoPHP.inc, which
 * includes the Geometry and adapter classes itself. Loading it here lets
 * PHPStan find those classes wherever the vendor directory is.
 */

class_exists(\geoPHP::class);
