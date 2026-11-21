<?php

declare(strict_types=1);

namespace ClubBilling\Http\Request;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;

final class JsonBody
{
    /** Small, flat bodies only. Deeply nested JSON is a cheap way to burn CPU. */
    private const int MAX_DEPTH = 8;

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function decode(ServerRequestInterface $request): array
    {
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            throw new ValidationException(['Content-Type' => 'Must be application/json.']);
        }

        try {
            $data = json_decode((string) $request->getBody(), true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationException(['body' => 'Malformed JSON.']);
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ValidationException(['body' => 'Must be a JSON object.']);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
