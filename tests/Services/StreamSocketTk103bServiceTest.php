<?php

use Illuminate\Support\Facades\Log;
use App\Services\StreamSocketTk103bService;
use App\Console\Commands\ServerGpsTK103bCommand;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

use App\Models\DataTk103b;
use App\Models\Gps;


class StreamSocketTk103bServiceTest extends TestCase
{
    use DatabaseMigrations;

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

    public function testListenMessageFromClientSendImeiExpectedResponseEqualON()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn('359710049095095');

        $serviceTest->shouldReceive('writeCommandInfo', 'fileWrite')
            ->andReturn(true);

        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertTrue($return, 'Esperasse que retorne true');

        $this->assertEquals('ON', $serviceTest->response, 'Esperasse que a resposta seja ON do server');
    }

    public function testListenMessageFromClientSendMessageExpectedResponseEqualLOAD()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn('##,imei:359710049095095,A');

        $serviceTest->shouldReceive('writeCommandInfo', 'fileWrite')
            ->andReturn(true);

        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertTrue($return, 'Esperasse que retorne true');

        $this->assertEquals('LOAD', $serviceTest->response, 'Esperasse que a resposta seja LOAD do server');
    }

    public function testListenMessageFromClientSendMessageFailedSaveDataGpsExpectedResponseFalse()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn('imei:359710049095095,tracker,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,');

        $serviceTest->shouldReceive('writeCommandInfo', 'fileWrite')
            ->andReturn(true);

        $modelTest = \Mockery::mock(DataTk103b::class)
            ->makePartial();
        $modelTest->shouldReceive('save')
            ->andReturn(false);

        $serviceTest->shouldReceive('getInstanceDataTk103b')
            ->andReturn($modelTest);

        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertFalse($return, 'Esperasse que retorne false após a falha');

        $this->assertEquals('', $serviceTest->response, 'Esperasse que não tenha resposta do server');
    }

    public function testListenMessageFromClientSendMessageSuccessSaveDataGpsExpectedResponseTrue()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn('imei:359710049095095,tracker,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,');

        $serviceTest->shouldReceive('writeCommandInfo', 'fileWrite')
            ->andReturn(true);

        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertTrue($return, 'Esperasse que retorne true');

        $this->assertEquals('', $serviceTest->response, 'Esperasse que não tenha resposta do server');

        $this->seeInDatabase('gps', ['imei' => '359710049095095']);
    }

    public function testListenMessageFromClientSendMessageContainingAlarmHelpMeSuccessSaveDataGpsExpectedResponseTrue()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn('imei:359710049095095,help me,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,');

        $serviceTest->shouldReceive('writeCommandInfo', 'fileWrite')
            ->andReturn(true);

        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertTrue($return, 'Esperasse que retorne true');

        $this->assertEquals('**,imei:359710049095095,E;', $serviceTest->response,
            'Esperasse que contenha a resposta com IMEI do client');

        $this->seeInDatabase('gps', ['imei' => '359710049095095']);
    }

    public function testListenMessageFromClientMessageSentIsEmptyExpectedResponseEqualEmptyAndReturnFalse()
    {
        $serviceTest = \Mockery::mock(StreamSocketTk103bService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $serviceTest->shouldReceive('fileRead')
            ->andReturn(false);

        $serviceTest->shouldReceive('writeCommandInfo', 'fileClose')
            ->andReturn(true);

        $serviceTest->client_sockets = [
            'clientOne' => 'dataFromClientOne'
        ];
        $serviceTest->read_sockets = [
            'ServerOne' => 'dataFromClientOne'
        ];

        $return = $serviceTest->listenMessageFromClient();

        $this->assertFalse($return, 'Esperasse que retorne false');

        $this->assertEquals('', $serviceTest->response,
            'Esperasse que não tenha resposta do server');

        $this->assertCount(0, $serviceTest->client_sockets,
            'Esperasse que não tenha cliente sendo escutado');
    }

}