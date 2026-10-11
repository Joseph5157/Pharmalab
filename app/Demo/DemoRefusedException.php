<?php

namespace App\Demo;

use RuntimeException;

/**
 * A refusal or conflict whose message is safe to show to the operator. It
 * never contains the demo password, SQL or database credentials.
 */
class DemoRefusedException extends RuntimeException {}
