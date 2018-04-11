<?php

use Illuminate\Support\Facades\Log;

class StreamSocketTk103bServiceTest extends TestCase
{
    public function testConstructFailedStreamSocketServerExpectedReturnFalse()
    {
        $return = new StreamSocketTk103bService();
    }
}