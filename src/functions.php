<?php
declare (strict_types = 1);

namespace Bileslav;

use ErrorException;
use ReflectionObject;
use Throwable;

function no_op(): void
{}

/**
 * For type hinting in VS Code where it doesn't work automatically.
 */
function callable_hint(callable $callback): callable
{
	return $callback;
}

function append_previous(Throwable $e, Throwable $previous): void
{
	// TODO Since this is a hack, tests are needed. Reflection?

	try {
		try {
			throw $previous;
		} finally {
			throw $e;
		}
	} catch (Throwable) {
		// No sense to return $e because the change occurs "by reference".
	}
}

function strict_errors(callable $callback, mixed ...$args): mixed
{
	set_error_handler(__NAMESPACE__ . '\\throw_error');

	try {
		return $callback(...$args);
	} finally {
		restore_error_handler();
	}
}

function flatten(Throwable $e): Throwable
{
	// TODO Tests would be nice.

	$reflection = new ReflectionObject($e);

	if ($reflection->isInternal() && $reflection->isFinal()) {
		return $e;
	}

	$previous = $e->getPrevious();
	$trace = $e->getTrace();

	if ($previous !== null) {
		$previous = flatten($previous);
	}

	foreach ($trace as &$call) {
		if (!array_key_exists('args', $call)) {
			continue;
		}

		array_walk_recursive($call['args'], __NAMESPACE__ . '\\flatten_argument');
	}

	$properties = [
		'code' => $e->getCode(),
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'message' => $e->getMessage(),
		'previous' => $previous,
		'trace' => $trace,
	];

	$e = $reflection->newInstanceWithoutConstructor();

	while (($parent_reflection = $reflection->getParentClass()) !== false) {
		$reflection = $parent_reflection;
	}

	foreach ($properties as $property => $value) {
		$reflection->getProperty($property)->setValue($e, $value);
	}

	return $e;
}

/**
 * @internal
 */
function throw_error(int $severity, string $message, string $file, int $line): never
{
	throw new ErrorException($message, 0, $severity, $file, $line);
}

/**
 * @internal
 */
function flatten_argument(mixed &$value): void
{
	if (is_object($value)) {
		$value = sprintf('object(%s)', get_class($value));
	} else if (is_resource($value)) {
		$value = sprintf('resource(%s)', get_resource_type($value));
	}
}
