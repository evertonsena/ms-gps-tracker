<?php

use Illuminate\Support\Facades\Log;
use App\Services\StreamSocketTk103bService;
use App\Console\Commands\ServerGpsTK103bCommand;

class StreamSocketTk103bServiceTest extends TestCase
{
    public function testServerFailedStreamSocketServerExpectedReturnFalse()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
                        ->shouldAllowMockingProtectedMethods()
                        ->makePartial();

        $serviceTest->shouldReceive('streamServer')
                    ->andReturn(false);

        $return = $serviceTest->server('0.0.0.0', '7331', 'tcp');


        $this->assertFalse($return, 'Esperasse que retorne false');
    }

    public function testServerSuccessStreamSocketServerExpectedReturnInstanceOfClass()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('streamServer')
            ->andReturn(true);

        $return = $serviceTest->server('0.0.0.0', '7331', 'tcp');


        $this->assertInstanceOf(StreamSocketTk103bService::class, $return,
            'Esperasse que retorne instancia da classe StreamSocketTk103bService');


    }

    public function testGetErrorExpectedReturnStringContentError()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('streamServer')
            ->andReturn(false);

        $serviceTest->server('0.0.0.0', '7331', 'tcp');
        $return = $serviceTest->getError();


        $this->assertEquals('stream_socket_server error: ', $return,
            'Esperasse que retorne string com erro');

    }

    public function testSetCommandExpectedReturnInstanceOfClass()
    {
        $return = (new StreamSocketTk103bService())->setCommand(new ServerGpsTK103bCommand());
        $this->assertInstanceOf(StreamSocketTk103bService::class, $return,
            'Esperasse que retorne instancia da classe StreamSocketTk103bService');

    }
}