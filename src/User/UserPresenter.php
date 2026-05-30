<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\User;

/**
 * Builds the public UserResponse shape. The password_hash and any token hashes
 * are never included (also keeps them out of audit snapshots).
 */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public static function present(User $user): array
    {
        return [
            'id'              => $user->id,
            'email'           => $user->email,
            'role'            => $user->role,
            'organization_id' => $user->organizationId,
            'status'          => $user->status,
            'created_at'      => $user->createdAt,
            'updated_at'      => $user->updatedAt,
        ];
    }
}
