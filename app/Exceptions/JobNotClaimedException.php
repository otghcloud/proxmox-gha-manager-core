<?php

namespace App\Exceptions;

/**
 * Raised when GitHub never assigned the triggering job to the runner we built for it.
 *
 * The VM is deliberately left running: it is a healthy idle runner that another job can
 * still claim. Only the job association is released so the work can be retried.
 */
class JobNotClaimedException extends ProvisioningException {}
