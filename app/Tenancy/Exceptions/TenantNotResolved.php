<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-scoped query runs with no business resolved.
 *
 * Fail-closed on purpose: an unresolved tenant means "show nothing", never
 * "show everything". Rendered as a 403 (see bootstrap/app.php).
 */
class TenantNotResolved extends RuntimeException {}
