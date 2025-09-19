<?php

namespace App\Constants;

/**
 * Route path constants to avoid duplicate string literals
 *
 * This class contains route path constants that are used multiple times
 * throughout the application to comply with SonarLint rules.
 */
class RouteConstants
{
    /**
     * Installer and update routes
     */
    public const UPDATE_PATH = '/update';

    /**
     * Dashboard routes
     */
    public const DASHBOARD_PATH = '/dashboard';

    /**
     * Settings routes
     */
    public const SETTINGS_PATH = '/settings';
    public const SETTINGS_PAGE_PATH = '/settings/page/{id}';

    /**
     * Helper method to get admin-prefixed route path
     */
    public static function adminPath(string $path): string
    {
        return '/admin' . $path;
    }

    /**
     * Helper method to get settings sub-path
     */
    public static function settingsSubPath(string $subPath): string
    {
        return self::SETTINGS_PATH . '/' . ltrim($subPath, '/');
    }
}
