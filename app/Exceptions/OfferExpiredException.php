<?php

namespace App\Exceptions;

class OfferExpiredException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Offer has expired.');
    }
}
