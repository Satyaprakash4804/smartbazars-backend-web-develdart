<?php

namespace App\Exceptions\SmartBazars;

use Exception;

/** An error whose message is safe and useful to show to the customer (e.g. "add ₹X to wallet for COD advance"). */
class UserFacingException extends Exception
{
}
