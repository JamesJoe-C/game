<?php
namespace App;

use Swoole;
use Swoole\Controller;

class Money extends Swoole\Controller
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
        $money = $this->_get_money();
        return ['bean'=>bcdiv($money,10)];
    }
    
    //获取用户当前金额
    private function _get_money(){
        $sql = 'select money from users where id='.$this->session['user_id'];
        $user_info = $this->db->query($sql)->fetch();
        return $user_info['money'];
    }
}
