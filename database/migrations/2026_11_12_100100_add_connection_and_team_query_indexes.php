<?php

use App\Support\Database\IndexMigration;

/**
 * Connected accounts and team invitations: tenant + platform lookups and the
 * invitation lookup by email that runs at every sign-in (workspace picker).
 */
return new class extends IndexMigration
{
    protected array $indexes = [
        // user_id = ? AND platform = ? (posting, ads, inbox, Hub). Serves the user_id foreign key.
        ['social_accounts', 'social_accounts_user_platform_index', ['user_id', 'platform'], 'social_accounts_user_id_foreign'],
        // WorkspaceService::invitations(): email = ? AND accepted_at IS NULL AND expires_at > now().
        ['seller_team_members', 'seller_team_members_email_index', ['email'], null],
    ];
};
