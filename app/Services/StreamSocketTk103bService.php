<?php
namespace App\Services;

use App\Console\Commands\ServerGpsTK103bCommand;

class StreamSocketTk103bService
{
    /**
     * Ip utilizado para prover o servidor
     * @var string
     */
    private $ip = '0.0.0.0';

    /**
     * Porta utilizada para prover o servidor
     * @var string
     */
    private $port = '7331';

    /**
     * Protocolo utilizado para prover o servidor
     * @var string
     */
    private $protocol = 'tcp';

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
     * StreamSocketTk103bService constructor.
     */
    public function __construct()
    {
        $this->server = stream_socket_server(
            "{$this->protocol}://{$this->ip}:$this->port", $errno, $errorMessage
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
     * Escuta a porta definida para comunicação com o rastreador
     * @param ServerGpsTK103bCommand $command
     * @return bool
     */
    public function listen(ServerGpsTK103bCommand $command)
    {
        $client_sockets = array();
        while (true) {
            // prepare readable sockets
            $read_sockets = $client_sockets;
            $read_sockets[] = $this->server;
            // start reading and use a large timeout
            if(!stream_select($read_sockets, $write, $except, 300000)) {
                $command->error('stream_select error.');
                return false;
            }
            // new client
            if(in_array($this->server, $read_sockets)) {
                $new_client = stream_socket_accept($this->server);
                if ($new_client) {
                    //print remote client information, ip and port number
                    $command->info('new connection: ' . stream_socket_get_name($new_client, true));
                    $client_sockets[] = $new_client;
                    $command->info("total clients: ". count($client_sockets));
                    // $output = "hello new client.\n";
                    // fwrite($new_client, $output);
                }
                //delete the server socket from the read sockets
                unset($read_sockets[ array_search($this->server, $read_sockets) ]);
            }
            // message from existing client
            foreach ($read_sockets as $socket) {
                $data = fread($socket, 128);

                $command->info("data: {$data}");
                $tk103_data = explode( ',', $data);
                $response = "";
                switch (count($tk103_data)) {
                    case 1: // 359710049095095 -> heartbeat requires "ON" response
                        $response = "ON";
                        $command->info('sent ON to client');
                        break;
                    case 3: // ##,imei:359710049095095,A -> this requires a "LOAD" response
                        if ($tk103_data[0] == "##") {
                            $response = "LOAD";
                            $command->info('sent LOAD to client');
                        }
                        break;
                    case 19: // imei:359710049095095,tracker,151006012336,,F,172337.000,A,5105.9792,N,11404.9599,W,0.01,322.56,,0,0,,,  -> this is our gps data
                        $imei = substr($tk103_data[0], 5);
                        $alarm = $tk103_data[1];
                        $gps_time = nmea_to_mysql_time($tk103_data[2]);
                        $latitude = degree_to_decimal($tk103_data[7], $tk103_data[8]);
                        $longitude = degree_to_decimal($tk103_data[9], $tk103_data[10]);
                        $speed_in_knots = $tk103_data[11];
                        $speed_in_mph = 1.15078 * $speed_in_knots;
                        $bearing = $tk103_data[12];
                        // **********
                        //insert_location_into_db($app, $imei, $gps_time, $latitude, $longitude, $speed_in_mph, $bearing);
                        // **********
                        if ($alarm == "help me") {
                            $response = "**,imei:" + $imei + ",E;";
                        }
                        break;
                }
                if (!$data) {
                    unset($client_sockets[ array_search($socket, $client_sockets) ]);
                    @fclose($socket);
                    $command->info("client disconnected. total clients: ". count($client_sockets));
                    continue;
                }
                //send the message back to client
                if (sizeof($response) > 0) {
                    fwrite($socket, $response);
                }
            }
        } // end while loop
    }
}