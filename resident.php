<?php

/**
 * Legacy singular URL — kept only so any bookmark, manuscript screenshot, or
 * printed handout pointing at /resident.php still lands somewhere sensible.
 *
 * This page used to be a second, standalone copy of the resident directory: it
 * carried its own inline stylesheet, its own copy of the insert/update/delete
 * logic, and its own markup, none of which was linked from the navigation. Two
 * copies of the same feature meant every fix had to be made twice, and this one
 * kept missing them — it never received the CSRF checks, the server-side
 * validation, or the responsive table that residents.php has.
 *
 * Its one unique feature, the gender filter, now lives on residents.php
 * alongside a voter-status filter, so nothing is lost by redirecting here.
 *
 * 301 rather than 302: the singular URL is not coming back.
 */

$query = $_GET;

// ?new=1 and ?edit=ID mean the same thing on the plural page, so carry them
// straight over; anything else is dropped rather than guessed at.
$allowed = array_intersect_key($query, array_flip(['new', 'edit', 'q', 'gender']));
$target = 'residents.php' . ($allowed ? '?' . http_build_query($allowed) : '');

header('Location: ' . $target, true, 301);
exit;
