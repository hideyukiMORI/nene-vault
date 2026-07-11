<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use DateTimeImmutable;
use DateTimeInterface;
use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginHandler
{
    public function __construct(
        private LoginUseCase $useCase,
        private JsonResponseFactory $response,
        private ?LoginThrottleInterface $throttle = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $errors = [];

        $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        if ($email === '') {
            $errors[] = new ValidationError('email', 'Email is required.', 'required');
        }

        if ($password === '') {
            $errors[] = new ValidationError('password', 'Password is required.', 'required');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Brute-force / credential-stuffing guard keyed on email + client IP (#148).
        $identifier = strtolower($email) . '|' . $this->clientIp($request);

        if ($this->throttle !== null) {
            $remaining = $this->throttle->secondsUntilUnlocked($identifier);

            if ($remaining > 0) {
                throw new TooManyLoginAttemptsException($remaining);
            }
        }

        try {
            $output = $this->useCase->execute(new LoginInput(email: $email, password: $password));
        } catch (InvalidCredentialsException $e) {
            $this->throttle?->recordFailure($identifier);

            throw $e;
        }

        $this->throttle?->clear($identifier);

        return $this->response->create([
            'token'      => $output->token,
            'expires_at' => (new DateTimeImmutable('@' . $output->expiresAt))->format(DateTimeInterface::ATOM),
            'user_id'    => $output->userId,
            'email'      => $output->email,
            'role'       => $output->role,
            'org_id'     => $output->orgId,
        ]);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();

        return (string) ($params['REMOTE_ADDR'] ?? 'unknown');
    }
}
