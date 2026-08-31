<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for anything that goes wrong while provisioning a runner.
 */
class ProvisioningException extends RuntimeException {}
