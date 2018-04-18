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

    public function testListenNotExistCommandExpectedReturnFalse()
    {
        $return = (new StreamSocketTk103bService())->listen();
        $this->assertFalse($return, 'Esperasse que retorne false quando não existe command');
    }

    public function testListenNoStreamSelectExpectedReturnFalse()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('streamSelectUp', 'writeCommandError')
                    ->andReturn(false);

        Log::shouldReceive('error')
            ->once()
            ->andReturn(true);

        $return = $serviceTest->setCommand(new ServerGpsTK103bCommand())
                              ->listen();

        $this->assertFalse($return, 'Esperasse que retorne false quando stream select retorne false');
    }

    public function testStreamForNewClientNotExistNewClientExpectedDeleteKeyFromReadSocket()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('streamAccept')
            ->andReturn(false);

        // populando dados de server
        $serviceTest->server = 'dataFromClientOne';
        // populando dados de leitura do socket
        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne',
            'ServerTwo' => 'dataFromClientTwo'
        ];

        $serviceTest->streamForNewClient();

        $this->assertFalse(isset($serviceTest->read_sockets[$serviceTest->server]),
            'Nao existindo novo cliente, deve ser excluido o server');
    }

    public function testStreamForNewClientExistNewClientExpectedDeleteKeyFromReadSocketAndCountOneClientSockets()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('streamAccept', 'writeCommandInfo', 'streamGetName')
            ->andReturn(true);

        // populando dados de server
        $serviceTest->server = 'dataFromClientOne';
        // populando dados de leitura do socket
        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne',
            'ServerTwo' => 'dataFromClientTwo'
        ];

        $serviceTest->streamForNewClient();

        $this->assertFalse(isset($serviceTest->read_sockets[$serviceTest->server]),
            'Após lido novo cliente, deve ser excluido o server');

        $this->assertCount(1, $serviceTest->client_sockets,
            'Esperasse que um cliente tenha sido lido');
    }
}