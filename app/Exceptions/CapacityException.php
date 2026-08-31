<?php

namespace App\Exceptions;

/**
 * Raised when a global or per-pool concurrency limit is already reached.
 *
 * The caller defers rather than fails: capacity is expected to free up.
 */
class CapacityException extends ProvisioningException {}
