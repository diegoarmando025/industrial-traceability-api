<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Subset of OpenAPI 3.0 schema validation required by our API contract.
 * Rejects missing required fields, incorrect scalar/array types, malformed
 * timestamps, invalid enums, and broken oneOf/ref declarations.
 */
final class OpenApiResponseValidator
{
    private array $document;

    public function __construct()
    {
        $this->document = json_decode(
            file_get_contents(base_path('docs/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function assertResponse(string $method, string $path, int $status, mixed $json): void
    {
        $operation = $this->document['paths'][$path][strtolower($method)] ?? null;
        Assert::assertNotNull($operation, "Undocumented API operation: {$method} {$path}");

        $response = $operation['responses'][(string) $status] ?? null;
        Assert::assertNotNull($response, "Undocumented HTTP {$status} for {$method} {$path}");

        $schema = $response['content']['application/json']['schema'] ?? null;
        Assert::assertNotNull($schema, "Missing application/json schema for {$status} {$method} {$path}");

        $this->validate($schema, $json, '$');
    }

    private function resolve(string $reference): array
    {
        Assert::assertStringStartsWith('#/', $reference);
        $node = $this->document;

        foreach (explode('/', substr($reference, 2)) as $piece) {
            $piece = str_replace(['~1', '~0'], ['/', '~'], $piece);
            Assert::assertArrayHasKey($piece, $node, "Missing OpenAPI reference {$reference}");
            $node = $node[$piece];
        }

        return $node;
    }

    private function validate(array $schema, mixed $value, string $path): void
    {
        if (isset($schema['$ref'])) {
            $this->validate($this->resolve($schema['$ref']), $value, $path);
            return;
        }

        if ($value === null && ($schema['nullable'] ?? false) === true) {
            return;
        }

        if (isset($schema['oneOf'])) {
            $matches = 0;
            foreach ($schema['oneOf'] as $candidate) {
                try {
                    $this->validate($candidate, $value, $path);
                    $matches++;
                } catch (\PHPUnit\Framework\AssertionFailedError $exception) {
                    // A oneOf alternative not matching is expected.
                }
            }
            Assert::assertSame(1, $matches, "{$path} must match exactly one of the contract alternatives");
            return;
        }

        if (isset($schema['enum'])) {
            Assert::assertContains($value, $schema['enum'], "{$path}: value not in enum");
        }

        $type = $schema['type'] ?? null;
        switch ($type) {
            case 'object':
                Assert::assertIsArray($value, "{$path} must be an object");
                Assert::assertFalse(array_is_list($value) && $value !== [], "{$path} must be an object, not a list");

                foreach ($schema['required'] ?? [] as $key) {
                    Assert::assertArrayHasKey($key, $value, "{$path}.{$key} missing");
                }
                foreach ($value as $key => $item) {
                    if (isset($schema['properties'][$key])) {
                        $this->validate($schema['properties'][$key], $item, "{$path}.{$key}");
                    } elseif (is_array($schema['additionalProperties'] ?? null)) {
                        $this->validate($schema['additionalProperties'], $item, "{$path}.{$key}");
                    } elseif (($schema['additionalProperties'] ?? true) === false) {
                        Assert::fail("{$path}.{$key} is not allowed");
                    }
                }
                break;

            case 'array':
                Assert::assertIsArray($value, "{$path} must be an array");
                Assert::assertTrue(array_is_list($value), "{$path} must be a list");
                foreach ($value as $index => $item) {
                    $this->validate($schema['items'], $item, "{$path}[{$index}]");
                }
                break;

            case 'integer':
                Assert::assertIsInt($value, "{$path} must be an integer");
                break;

            case 'number':
                Assert::assertTrue(is_int($value) || is_float($value), "{$path} must be numeric");
                break;

            case 'boolean':
                Assert::assertIsBool($value, "{$path} must be boolean");
                break;

            case 'string':
                Assert::assertIsString($value, "{$path} must be a string");
                if (($schema['format'] ?? '') === 'date-time') {
                    Assert::assertNotFalse(strtotime($value), "{$path} must be a date-time");
                }
                break;

            case null:
                // oneOf and local references above cover schemas without a type.
                break;

            default:
                throw new RuntimeException("Unsupported OpenAPI type {$type} at {$path}");
        }
    }
}
