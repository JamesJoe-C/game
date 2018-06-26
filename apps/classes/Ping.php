<?php
namespace App;

use Swoole;
use Swoole\Controller;

class Ping extends Swoole\Controller
{
    private $session;
 
    private $websocket;
    
    public function __construct($swoole, $session, &$websocket_server)
    {
        parent::__construct($swoole);
        $this->session = $session; 
        $this->websocket = $websocket_server;
        
    }
    
    public function index($data)
    {
        return ;
    }
}
