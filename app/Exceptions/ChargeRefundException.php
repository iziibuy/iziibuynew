<?php

namespace App\Exceptions;

use Exception;

/**
 * A charge refund was refused or failed; the message is safe to show to admins.
 */
class ChargeRefundException extends Exception {}
