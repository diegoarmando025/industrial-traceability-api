#!/usr/bin/env python3
"""Validate the repository's OpenAPI 3.0 contract without external dependencies.

Checks JSON syntax, supported local $refs, path parameters, operation IDs,
Bearer security, common error responses and response/request schemas.
Laravel route parity is checked separately by OpenApiContractTest.php.
"""
import json
import sys
from pathlib import Path

SPEC = Path(__file__).resolve().parents[1] / "docs" / "openapi.json"
HTTP_METHODS = {"get", "post", "put", "patch", "delete", "head", "options", "trace"}


def fail(message: str) -> None:
    raise ValueError(message)


def local_ref(document: dict, pointer: str):
    if not pointer.startswith("#/"):
        fail(f"External/invalid reference is not supported: {pointer}")
    current = document
    for segment in pointer[2:].split("/"):
        segment = segment.replace("~1", "/").replace("~0", "~")
        try:
            current = current[int(segment)] if isinstance(current, list) else current[segment]
        except (KeyError, IndexError, ValueError, TypeError):
            fail(f"Unresolvable $ref: {pointer}")
    return current


def check_schema(document: dict, schema, context: str, seen: set[str]) -> None:
    if not isinstance(schema, dict):
        fail(f"{context}: expected an object schema")
    if "$ref" in schema:
        pointer = schema["$ref"]
        target = local_ref(document, pointer)
        if pointer not in seen:
            check_schema(document, target, pointer, seen | {pointer})
        return

    for combinator in ("allOf", "anyOf", "oneOf"):
        if combinator in schema:
            members = schema[combinator]
            if not isinstance(members, list) or not members:
                fail(f"{context}: {combinator} must be a nonempty list")
            for index, member in enumerate(members):
                check_schema(document, member, f"{context}.{combinator}[{index}]", seen)

    if schema.get("type") == "array":
        if "items" not in schema:
            fail(f"{context}: array must define items")
        check_schema(document, schema["items"], context + ".items", seen)

    if schema.get("type") == "object":
        properties = schema.get("properties", {})
        if not isinstance(properties, dict):
            fail(f"{context}: properties must be an object")
        for key in schema.get("required", []):
            if key not in properties:
                fail(f"{context}: required field {key!r} is missing from properties")
        for key, field in properties.items():
            check_schema(document, field, f"{context}.{key}", seen)
        additional = schema.get("additionalProperties")
        if isinstance(additional, dict):
            check_schema(document, additional, context + ".additionalProperties", seen)


def check_content(document: dict, payload: dict, context: str) -> None:
    for media_type, media in payload.get("content", {}).items():
        if "schema" not in media:
            fail(f"{context}: {media_type} needs a schema")
        check_schema(document, media["schema"], context + f".{media_type}", set())


def main() -> int:
    document = json.loads(SPEC.read_text(encoding="utf-8"))
    if document.get("openapi") != "3.0.3":
        fail("This project expects OpenAPI 3.0.3")

    components = document.get("components", {})
    schemes = components.get("securitySchemes", {})
    bearer = schemes.get("sanctumBearer")
    if not isinstance(bearer, dict) or bearer.get("type") != "http" or bearer.get("scheme") != "bearer":
        fail("sanctumBearer must be an HTTP Bearer security scheme")

    for name, schema in components.get("schemas", {}).items():
        check_schema(document, schema, f"components.schemas.{name}", set())

    operations = set()
    operation_ids = set()
    for path, path_item in document.get("paths", {}).items():
        if not path.startswith("/api/v1/"):
            fail(f"API path must be versioned under /api/v1/: {path}")
        parameters = {piece[1:-1] for piece in path.split("/") if piece.startswith("{") and piece.endswith("}")}
        for method, operation in path_item.items():
            if method not in HTTP_METHODS:
                continue
            key = (method.upper(), path)
            if key in operations:
                fail(f"Duplicate operation {key}")
            operations.add(key)
            operation_id = operation.get("operationId")
            if not operation_id or operation_id in operation_ids:
                fail(f"Missing or duplicated operationId at {key}")
            operation_ids.add(operation_id)

            documented_parameters = {
                param.get("name") for param in operation.get("parameters", path_item.get("parameters", []))
                if param.get("in") == "path" and param.get("required") is True
            }
            if parameters != documented_parameters:
                fail(f"{key}: path parameters disagree: {parameters} vs {documented_parameters}")

            security = operation.get("security", document.get("security", []))
            if not any("sanctumBearer" in requirement for requirement in security):
                fail(f"{key}: missing Sanctum Bearer authentication")

            responses = operation.get("responses", {})
            for status in ("401", "403", "404"):
                if status not in responses:
                    fail(f"{key}: missing {status} response")
            for status, response in responses.items():
                check_content(document, response, f"{key} response {status}")
            if "requestBody" in operation:
                check_content(document, operation["requestBody"], f"{key} requestBody")

    if not operations:
        fail("No API operations found")
    print(f"OpenAPI contract: OK ({len(operations)} operations; {len(components.get('schemas', {}))} schemas)")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        print(f"OpenAPI contract: FAILED — {exc}", file=sys.stderr)
        sys.exit(1)
