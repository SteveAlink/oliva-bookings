<?php
/**
 * oliva-bookings - Bookings plugin for WonderCMS.
 * Prepared by Steve Alink for Oliva Solutions
 *
 * Adds simple no-payment reservations for Oliva Events dates.
 */

if (!defined('VERSION')) {
    die('Direct access denied');
}

require_once __DIR__ . '/class.oliva-bookings.php';

$olivaBookings = new OlivaBookings($Wcms);

if (isset($Wcms)) {
    $Wcms->addListener('css', 'olivaBookingsCss');
    $Wcms->addListener('js', 'olivaBookingsJs');
    $Wcms->addListener('settings', [$olivaBookings, 'handleSettings']);
    $Wcms->addListener('footer', [$olivaBookings, 'renderBookings']);
    $Wcms->addListener('page', [$olivaBookings, 'replacePlaceholder']);
} elseif (class_exists('wCMS')) {
    wCMS::addListener('css', 'olivaBookingsCss');
    wCMS::addListener('js', 'olivaBookingsJs');
    wCMS::addListener('settings', [$olivaBookings, 'handleSettings']);
    wCMS::addListener('footer', [$olivaBookings, 'renderBookings']);
    wCMS::addListener('page', [$olivaBookings, 'replacePlaceholder']);
}

function olivaBookingsPluginBasePath()
{
    return 'plugins/oliva-bookings/';
}

function olivaBookingsCss($args)
{
    $css = '<link rel="stylesheet" href="' . olivaBookingsPluginBasePath() . 'css/style.css?v=0.1.0">' . PHP_EOL;

    if (isset($args[0]) && is_array($args[0])) {
        $args[0][] = $css;
    } else {
        $args[0] = ($args[0] ?? '') . $css;
    }

    return $args;
}

function olivaBookingsJs($args)
{
    $js = '<script src="' . olivaBookingsPluginBasePath() . 'js/oliva-bookings.js?v=0.1.0"></script>' . PHP_EOL;

    if (isset($args[0]) && is_array($args[0])) {
        $args[0][] = $js;
    } else {
        $args[0] = ($args[0] ?? '') . $js;
    }

    return $args;
}
