<?php

namespace App\Services\Connections;

use App\Models\SocialConnection;
use Symfony\Component\HttpFoundation\Response;

/**
 * One per platform (docs/connection-hub-design.md §3). Drivers reuse the
 * existing services' HTTP code; they only add the connection lifecycle.
 */
interface ProviderDriver
{
    public function platform(): string;

    public function label(): string;

    /**
     * How the Hub draws the card: subtitle, brand icons, primary button
     * label, empty-state copy and which capabilities to pitch.
     *
     * @return array{subtitle: string, icons: array<int, array{icon: string, brand: string}>,
     *               connect_label: string, empty_title: string, empty_text: string, benefits: string[]}
     */
    public function presentation(): array;

    /**
     * The card's connect steps, built from feature flags.
     *
     * @return array<int, array{key: string, label: string, description: string,
     *                          primary: bool, available: bool, note?: ?string}>
     */
    public function steps(): array;

    /** Start the provider consent for a step (redirect, or a page that runs it). */
    public function connect(string $step): Response;

    /**
     * Ask the provider whether the connection still works: updates
     * granted_scopes, capabilities, status, last_checked_at and last_error.
     */
    public function validate(SocialConnection $connection): SocialConnection;

    /** Revoke at the provider (best-effort) and locally. */
    public function disconnect(SocialConnection $connection): void;
}
