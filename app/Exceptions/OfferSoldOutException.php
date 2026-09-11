<?php

namespace App\Exceptions;

class OfferSoldOutException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Offer has no available units left.');
    }
}
