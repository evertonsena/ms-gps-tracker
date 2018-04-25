<?php
namespace App\Services;

use App\Console\Commands\ServerGpsTK103bCommand;
use App\Models\DataTk103b;
use Illuminate\Support\Facades\Log;

class StreamSocketTk103bService
{
    /**
     * Armazena o ultimo erro ocorrido. Utilize $instance->getError() para acessar externamente;
     * @var string
     */
    private $error;

    /**
     * Recurso do stream_socket_server
     * @var resource
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public $server;

    /**
     * @var ServerGpsTK103bCommand
     */
    private $command;

    /**
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public $read_sockets;

    /**
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public $client_sockets;

    /**
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public $response;

    private $errorStreamServerMessage;

    private $errnoStreamServer;

    private $alarm;

    private $imei;

    /**
     * Sobe server stream socket.
     * @param $ip - Ip utilizado para prover o servidor
     * @param $port - Porta utilizada para prover o servidor
     * @param $protocol - Protocolo utilizado para prover o servidor
     */
    public function server($ip, $port, $protocol)
    {
        $this->server = $this->streamServer($ip, $port, $protocol);
        if ($this->server === false) {
            $this->error = "stream_socket_server error: {$this->errorStreamServerMessage}";
            return false;
        }
        return $this;
    }

    /**
     * StreamSocketTk103bService constructor.
     * @param $ip - Ip utilizado para prover o servidor
     * @param $port - Porta utilizada para prover o servidor
     * @param $protocol - Protocolo utilizado para prover o servidor
     * @codeCoverageIgnore
     */
    protected function streamServer($ip, $port, $protocol)
    {
        return stream_socket_server(
            "{$protocol}://{$ip}:$port", $this->errnoStreamServer, $this->errorStreamServerMessage
        );
    }

    /**
     * Retorna o ultimo erro ocorrido
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Seta o command
     * @param ServerGpsTK103bCommand $command
     * @return $this
     */
    public function setCommand(ServerGpsTK103bCommand $command)
    {
        $this->command = $command;
        return $this;
    }

    private function streamSelect()
    {
        // prepare readable sockets
        $this->read_sockets = $this->client_sockets;
        $this->read_sockets[] = $this->server;
        // start reading and use a large timeout
        if (!$this->streamSelectUp()) {
            Log::error('Falha no stream_select');
            $this->writeCommandError('stream_select error.');
            return false;
        }
        // @codeCoverageIgnoreStart
        return true;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @codeCoverageIgnore
     */
    protected function streamSelectUp()
    {
        return stream_select($this->read_sockets, $write, $except, 300000);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function writeCommandError($message)
    {
        $this->command->error($message);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function writeCommandInfo($message)
    {
        $this->command->info($message);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function streamAccept()
    {
        return stream_socket_accept($this->server);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function streamGetName($new_client)
    {
        return stream_socket_get_name($new_client, true);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function fileRead($socket)
    {
        return fread($socket, 128);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function fileWrite($socket)
    {
        return fwrite($socket, $this->response);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function fileClose($socket)
    {
        return @fclose($socket);
    }

    /**
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public function streamForNewClient()
    {
        // new client
        if (in_array($this->server, $this->read_sockets)) {
            $new_client = $this->streamAccept();
            if ($new_client) {
                $this->writeCommandInfo('new connection: ' . $this->streamGetName($new_client));
                $this->client_sockets[] = $new_client;
                $this->writeCommandInfo("total clients: " . count($this->client_sockets));
            }
            //delete the server socket from the read sockets
            unset($this->read_sockets[array_search($this->server, $this->read_sockets)]);
        }
    }

    /**
     * TODO: Alterado visibilidade para public para ser testado. Não deve ser acessado por fora
     */
    public function listenMessageFromClient()
    {
        // message from existing client
        foreach ($this->read_sockets as $socket) {
            $data = $this->fileRead($socket);

            if (!$data) {
                unset($this->client_sockets[array_search($socket, $this->client_sockets)]);
                $this->fileClose($socket);
                $this->writeCommandInfo("client disconnected. total clients: " . count($this->client_sockets));
                return false;
            }

            $this->writeCommandInfo("data: {$data}");
            $tk103_data = explode(',', $data);

            $this->response = "";
            switch (count($tk103_data)) {
                case 1: // 359710049095095 -> heartbeat requires "ON" response
                    $this->response = "ON";
                    $this->writeCommandInfo('sent ON to client');
                    break;
                case 3: // ##,imei:359710049095095,A -> this requires a "LOAD" response
                    if ($tk103_data[0] == "##") {
                        $this->response = "LOAD";
                        $this->writeCommandInfo('sent LOAD to client');
                    }
                    break;
                case 19: // imei:359710049095095,tracker,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,  -> this is our gps data

                    if (!$this->saveDataFromGps($tk103_data)) {
                        return false;
                    }
                    if ($this->alarm == "help me") {
                        $this->response = "**,imei:" . $this->imei . ",E;";
                    }
                    break;
            }
            //send the message back to client
            if (!empty($this->response)) {
                $this->fileWrite($socket);
            }

            return true;
        }
// @codeCoverageIgnoreStart
// bug do coverage que reconhece a linha abaixo como não testado
    }
// @codeCoverageIgnoreEnd

    protected function getInstanceDataTk103b()
    {
        return new DataTk103b();
    }

    protected function saveDataFromGps($tk103_data)
    {
        $dataTk103b = $this->getInstanceDataTk103b();
        $dataTk103b->setData($tk103_data);
        if(!$dataTk103b->save()) {
            return false;
        }
        $this->alarm = $dataTk103b->getAlarm();
        $this->imei = $dataTk103b->getImei();

        return true;
    }

    /**
     * Escuta a porta definida para comunicação com o rastreador
     * @param ServerGpsTK103bCommand $command
     * @return bool
     */
    public function listen()
    {

        if (!$this->command) {
            return false;
        }

        $this->client_sockets = [];
        while (true) {

            if (!$this->streamSelect()) {
                return false;
            }
            // @codeCoverageIgnoreStart
            $this->streamForNewClient();
            if (!$this->listenMessageFromClient()) {
                continue;
            }
        }
    }
            // @codeCoverageIgnoreEnd
}
