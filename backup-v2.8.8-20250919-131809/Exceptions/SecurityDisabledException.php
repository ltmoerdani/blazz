<?php

namespace App\Exceptions;

use Exception;

class SecurityDisabledException extends Exception
{
    public function __construct($message = 'This operation has been disabled for security reasons.', $code = 403, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
