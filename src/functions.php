<?php
declare (strict_types = 1);

namespace Bileslav;

use ErrorException;
use ReflectionObject;
use Throwable;

function no_op(): void
{}

function retval(mixed $value): mixed
{
	return $value;
}

/**
 * For type hinting in VS Code where it doesn't work automatically.
 */
function callable_hint(callable $callback): callable
{
	return $callback;
}

function call_if_not_null(callable $callback, mixed $argument): mixed
{
	return is_null($argument) ? null : $callback($argument);
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

function strict_errors(callable $callback, mixed ...$arguments): mixed
{
	set_error_handler(__NAMESPACE__ . '\\throw_error');

	try {
		return $callback(...$arguments);
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

	$properties = [
		'code' => $e->getCode(),
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'message' => $e->getMessage(),
		'previous' => call_if_not_null(__NAMESPACE__ . '\\flatten', $e->getPrevious()),
		'trace' => $e->getTrace(),
	];

	foreach ($properties['trace'] as &$call) {
		if (array_key_exists('args', $call)) {
			array_walk_recursive($call['args'], __NAMESPACE__ . '\\flatten_argument');
		}
	}

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
	$value = match (true) {
		is_object($value) => sprintf('object(%s)', get_class($value)),
		is_resource($value) => sprintf('resource(%s)', get_resource_type($value)),
		default => $value,
	};
}
