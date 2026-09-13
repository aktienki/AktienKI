<?php

namespace App\Services;

/**
 * The real bull logo used across the app (topbar, dashboard) - dark variant
 * (light bull outline + silver "aktienKI.com" wordmark on a transparent
 * background), matching the dark header strip every transactional email
 * uses. Previously this rendered a crude GD-drawn placeholder; emails now
 * embed the same asset as everywhere else in the product.
 */
final class RecommendationEmailLogo
{
    public function render(): string
    {
        return (string) file_get_contents(public_path('brand/generated/bull-logo-dark.png'));
    }
}
