<?php

namespace App\Services;

class DeviceDetectorService
{
    /**
     * Detect device category from user agent.
     */
    public function getDeviceCategory(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Desktop';
        }

        $userAgent = strtolower($userAgent);

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
            return 'Tablet';
        }

        if (preg_match('/(mobi|iphone|ipod|blackberry|opera mini|fennec|minimo|symbian|psp|wap|midp)/i', $userAgent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    /**
     * Detect operating system from user agent.
     */
    public function getOperatingSystem(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Outro';
        }

        $userAgent = strtolower($userAgent);

        $os = [
            'windows' => 'Windows',
            'macintosh|mac os x' => 'macOS',
            'iphone|ipad|ipod' => 'iOS',
            'android' => 'Android',
            'linux' => 'Linux',
        ];

        foreach ($os as $pattern => $name) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                return $name;
            }
        }

        return 'Outro';
    }
}
