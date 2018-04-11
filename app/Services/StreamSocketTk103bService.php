<?php
namespace App\Services;

use App\Console\Commands\ServerGpsTK103bCommand;
use App\Models\DataTk103b;

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
     */
    private $server;

    /**
     * @var ServerGpsTK103bCommand
     */
    private $command;
    
    private $read_sockets;

    private $client_sockets;

    /**
     * StreamSocketTk103bService constructor.
     * @param $ip - Ip utilizado para prover o servidor
     * @param $port - Porta utilizada para prover o servidor
     * @param $protocol - Protocolo utilizado para prover o servidor
     */
    public function __construct($ip, $port, $protocol)
    {
        $this->server = stream_socket_server(
            "{$protocol}://{$ip}:$port", $errno, $errorMessage
        );
        if ($this->server === false) {
            $this->error = "stream_socket_server error: {$errorMessage}";
            return false;
        }
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
        if(!stream_select($this->read_sockets, $write, $except, 300000)) {
            Log::error('Falha no stream_select');
            $this->command->error('stream_select error.');
            return false;
        }
        return true;
    }

    private function streamForNewClient()
    {
        // new client
        if(in_array($this->server, $this->read_sockets)) {
            $new_client = stream_socket_accept($this->server);
            if ($new_client) {
                //print remote client information, ip and port number
                $this->command->info('new connection: ' . stream_socket_get_name($new_client, true));
                $this->client_sockets[] = $new_client;
                $this->command->info("total clients: ". count($this->client_sockets));
                // $output = "hello new client.\n";
                // fwrite($new_client, $output);
            }
            //delete the server socket from the read sockets
            unset($this->read_sockets[ array_search($this->server, $this->read_sockets) ]);
        }
    }

    private function listenMessageFromClient()
    {
        // message from existing client
        foreach ($this->read_sockets as $socket) {
            $data = fread($socket, 128);

            $this->command->info("data: {$data}");
            $tk103_data = explode( ',', $data);
            $response = "";
            switch (count($tk103_data)) {
                case 1: // 359710049095095 -> heartbeat requires "ON" response
                    $response = "ON";
                    $this->command->info('sent ON to client');
                    break;
                case 3: // ##,imei:359710049095095,A -> this requires a "LOAD" response
                    if ($tk103_data[0] == "##") {
                        $response = "LOAD";
                        $this->command->info('sent LOAD to client');
                    }
                    break;
                case 19: // imei:359710049095095,tracker,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,  -> this is our gps data

                    $dataTk103b = new DataTk103b();
                    $dataTk103b->setData($tk103_data);
                    if(!$dataTk103b->save())
                    {
                        return false;
                    }
                    $alarm = $dataTk103b->getAlarm();
                    $imei = $dataTk103b->getImei();
                    // **********
                    //insert_location_into_db($app, $imei, $gps_time, $latitude, $longitude, $speed_in_mph, $bearing);
                    // **********
                    if ($alarm == "help me") {
                        $response = "**,imei:" + $imei + ",E;";
                    }
                    break;
            }
            if (!$data) {
                unset($this->client_sockets[ array_search($socket, $this->client_sockets) ]);
                @fclose($socket);
                $this->command->info("client disconnected. total clients: ". count($this->client_sockets));
                return false;
            }
            //send the message back to client
            if (sizeof($response) > 0) {
                fwrite($socket, $response);
            }

            return true;
        }
    }

    /**
     * Escuta a porta definida para comunicação com o rastreador
     * @param ServerGpsTK103bCommand $command
     * @return bool
     */
    public function listen()
    {

        if(!$this->command) {
            return false;
        }

        $this->client_sockets = [];
        while (true) {

            if(!$this->streamSelect()) {
                return false;
            }

            $this->streamForNewClient();
            if(!$this->listenMessageFromClient())
            {
                continue;
            }
        } // end while loop
    }
}