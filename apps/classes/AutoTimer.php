<?php
namespace App;

use Swoole;
use Swoole\Controller;

class AutoTimer extends Swoole\Controller
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
        $start_time = $this->_get_issue_id();
       
        return ['issue_time'=>$start_time];
    }
    // 获取当前期号的开始时间
    private function _get_issue_id()
    {
        $sql = 'select * from cdx_issues where status=0';
        $issues_rs = $this->db->query($sql);
        if ($issues_rs) {
            $issues = $issues_rs->fetchall();
            echo '客户端获取当前期号剩余开奖时间：';
            //var_dump($issues);
            if (isset($issues[1])) {  
               return ;
            }
            //var_dump($issues[0]['start_time']);
            //var_dump(date('Y-m-d H:i:s'));
            $time = (60-(time()-strtotime($issues[0]['start_time'])));
            //var_dump($time);
            return ($time<0)?60:$time;
        } else {
            return;
        }
        return;
    }
}
