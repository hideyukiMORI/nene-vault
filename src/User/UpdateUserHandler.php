<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateUserHandler
{
    /** @var list<string> */
    private const VALID_STATUSES = ['active', 'invited'];

    public function __construct(
        private UpdateUserUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $actorUserId = RequestContext::actorUserId($request);

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $body = JsonRequestBodyParser::parse($request);

        $email = isset($body['email']) && is_string($body['email']) && $body['email'] !== '' ? trim($body['email']) : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException([new ValidationError('email', 'A valid email is required.', 'invalid_email')]);
        }

        $role = isset($body['role']) && is_string($body['role']) && $body['role'] !== '' ? $body['role'] : null;

        $status = isset($body['status']) && is_string($body['status']) && $body['status'] !== '' ? $body['status'] : null;
        if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
            throw new ValidationException([new ValidationError('status', 'Invalid status.', 'invalid_format')]);
        }

        $user = $this->useCase->execute($id, $orgId, $email, $role, $status, $actorUserId);

        return $this->response->create(UserPresenter::present($user));
    }
}
