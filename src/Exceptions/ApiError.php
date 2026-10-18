<?php

declare(strict_types=1);

namespace Paddle\SDK\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

class ApiError extends \Exception implements ClientExceptionInterface
{
    /** @var array<FieldError> */
    public array $fieldErrors;

    public int|null $retryAfter = null;

    /** The request_id Paddle gives every answer: Paddle's support finds the request by it */
    public string|null $requestId = null;

    final public function __construct(
        public string $type,
        public string $errorCode,
        public string $detail,
        public string $docsUrl,
        FieldError ...$fieldErrors,
    ) {
        $this->fieldErrors = $fieldErrors;

        parent::__construct($this->detail);
    }

    public static function fromErrorData(array $error, int|null $retryAfter = null, string|null $requestId = null): static
    {
        $apiError = new static(
            $error['type'],
            $error['code'],
            $error['detail'],
            $error['documentation_url'],
            ...array_map(
                fn (array $fieldError): FieldError => new FieldError($fieldError['field'], $fieldError['message']),
                $error['errors'] ?? [],
            ),
        );

        $apiError->retryAfter = $retryAfter;
        $apiError->requestId = $requestId;

        return $apiError;
    }
}
