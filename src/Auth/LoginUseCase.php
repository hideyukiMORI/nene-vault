<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Http\ClockInterface;

final readonly class LoginUseCase
{
    public const int TOKEN_TTL_SECONDS = 3600; // 1 hour — fleet standard (#148)

    /**
     * A valid bcrypt hash used to equalize timing when the email is unknown,
     * so a failed login does not reveal whether the account exists (#150).
     */
    private const string TIMING_EQUALIZER_HASH = '$2y$12$zIm0IdtQKFLbeCP4lZhm7upwJ7hz/JAj4krfZ53eGCIVzLq82RwP6';

    public function __construct(
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokenIssuer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->users->findByEmail($input->email);

        // Always run password_verify — against a fixed dummy hash when the
        // email is unknown — so the response time does not leak whether the
        // account exists.
        $hash = $user !== null ? $user->passwordHash : self::TIMING_EQUALIZER_HASH;
        $passwordMatches = password_verify($input->password, $hash);

        // Only `active` users may log in: `invited` users must accept the
        // invite first, and any future deactivated state must not authenticate.
        if ($user === null || $user->status !== 'active' || !$passwordMatches) {
            throw new InvalidCredentialsException();
        }

        $role = Role::tryFrom($user->role);

        if ($role === null) {
            throw new InvalidCredentialsException();
        }

        $now = $this->clock->now()->getTimestamp();
        $expiresAt = $now + self::TOKEN_TTL_SECONDS;

        // superadmin は組織に属さないため org は null。
        // admin / member / viewer は所属組織の ID を JWT に埋め込む。
        // Claims follow the fleet-standard schema (#150): sub = user id, org.
        $orgId = $role === Role::Superadmin ? null : $user->organizationId;

        $token = $this->tokenIssuer->issue([
            'sub'  => $user->id,
            'role' => $role->value,
            'org'  => $orgId,
            'iat'  => $now,
            'exp'  => $expiresAt,
        ]);

        return new LoginOutput(
            token: $token,
            expiresAt: $expiresAt,
            email: $user->email,
            role: $role->value,
            orgId: $orgId,
            userId: $user->id,
        );
    }
}
