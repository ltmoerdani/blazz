<?php

namespace App\Exceptions;

use Exception;

class TemplateNotFoundException extends Exception
{
    public function __construct($message = 'Template not found', $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
