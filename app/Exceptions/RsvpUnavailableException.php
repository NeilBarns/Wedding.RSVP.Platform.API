<?php

namespace App\Exceptions;

use RuntimeException;

class RsvpUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This invitation is no longer accepting RSVP responses.');
    }
}
