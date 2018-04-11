<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StreamSocketTk103bService;

class ServerGpsTK103bCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'server-gps-tk103b:command';

    /**
     * The console comand description.
     *
     * @var string
     */
    protected $description = 'Sobe o servidor para escutar os rastreadores modelo TK103b';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $ip = env('IP_SERVER', '0.0.0.0');
        $port = env('PORT_SERVER', '7331');
        $protocol = env('PROTOCOL_SERVER', 'tcp');

        if(!$server = new StreamSocketTk103bService($ip, $port, $protocol)) {
            $this->error($server->getError());
            return false;
        }

        $this->info('Servidor no ar!');

        $server->setCommand($this)
               ->listen();
    }
}