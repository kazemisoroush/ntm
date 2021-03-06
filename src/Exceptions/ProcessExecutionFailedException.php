<?php

namespace Ntcm\Exceptions;

use Exception;

class ProcessExecutionFailedException extends Exception {

    /**
     * @var integer error code.
     */
    protected $code = 400;

    /**
     * Create instance of class
     *
     * @param string $message
     */
    public function __construct($message = "Scan run failed.")
    {
        parent::__construct($message, $this->code);
    }
}