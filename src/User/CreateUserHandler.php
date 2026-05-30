<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private CreateUserUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        $body = JsonRequestBodyParser::parse($request);
        $errors = [];

        $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';
        $role = isset($body['role']) && is_string($body['role']) ? $body['role'] : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = new ValidationError('email', 'A valid email is required.', 'invalid_email');
        }

        if (strlen($password) < 8) {
            $errors[] = new ValidationError('password', 'Password must be at least 8 characters.', 'too_small');
        }

        if ($role === '') {
            $errors[] = new ValidationError('role', 'Role is required.', 'required');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->useCase->execute($email, $password, $role, $orgId, $actorUserId);

        return $this->response->create(UserPresenter::present($user), 201);
    }
}
