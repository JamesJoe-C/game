<?php
define('DEBUG', 'on');
define("WEBPATH", str_replace("\\", "/", __DIR__));
require __DIR__ . '/libs/lib_config.php';

class WebSocket extends Swoole\Protocol\CometServer
{

    protected $message;

    private $timerTick;
    
    private $game_id = 2;
    
    private $timer_num = 1;
    
    /**
     *
     * @param $serv swoole_server            
     * @param int $worker_id            
     */
    function onStart($serv, $worker_id = 0)
    {
        parent::onStart($serv, $worker_id);
    }

    /**
     * 退出时，删除房间数据
     */
    function onExit($client_id)
    {
        try {
            echo "\n-------------------------Onexit------------------------------\n";
            echo "\n".'【user:'.$this->sessions['user_id'].'exit】'."\n";
            $onexit = new \App\Onexit(Swoole::$php);
            $onexit->to($client_id,$this);
            echo "\n-----------------------Onexit end-----------------------------\n";
        } catch (\Exception $e) {
            echo "\n-------------------------Onexit error------------------------------\n";
            var_dump($e->getMessage().$e->getTraceAsString());
            echo "\n-----------------------Onexit error end-----------------------------\n";
        }
        
    }

    /**
     * 接收到消息时
     */
    // {"username":"admin","password":"123456"}
    function onMessage($client_id, $ws)
    {
        //$this->my_log_start();
        //var_dump($this->connections[$client_id]);
        echo "\n接收到消息：\n";
        var_dump($this->currentRequest->server['REMOTE_ADDR']);
        //var_dump($this->server);
        file_put_contents('/home/wwwroot/lnmp_nginx1.12/domain/gameserver/remote_id', print_r($this->server,true));
        $message = json_decode($ws['message'], true);
        
        // 验证是否为json
        if (json_last_error() != JSON_ERROR_NONE) {
            echo "\n json格式错误 \n";
            $this->send($client_id, 'fuck you', true);
        } else {
            include_once 'apps/app.php';
            
            $this->sessions[$client_id]['client_id'] = $client_id;
            $this->sessions[$client_id]['game_id']   = 2;
            $this->sessions[$client_id]['ip']        = $this->currentRequest->server['REMOTE_ADDR'];
            //var_dump(self::$session);
            //echo 'session值为：';
            //var_dump($this->sessions);
            $app = new App($this->sessions[$client_id], Swoole::$php, $this);
            $this->send($client_id, $app->go($message), true);
            //var_dump($this->sessions);
        }
        //$this->my_log_end($client_id);
    }

    function broadcast($client_id, $msg)
    {
        foreach ($this->connections as $clid => $info) {
            if ($client_id != $clid) {
                $this->send($clid, $msg);
            }
        }
    }

    function my_log_start()
    {
        ob_start();
    }

    function my_log_end($client_id)
    {
        
        $uid = $this::$session[$client_id]['uid'];
        $role = $this::$session[$client_id]['role'];
        $log_str  = "\n=========================================" . '客户端id：' . $uid . '.用户id：'.$this->sessions[$client_id]['user_id'] . "=========================================\n";
        $log_str .= ob_get_contents();
        $log_str .= "\n=======================================================================end===============================================================================\n";
        ob_end_clean();
        file_put_contents('/home/wwwroot/lnmp_nginx1.12/domain/gameserver/log/log', $log_str, FILE_APPEND);
    }
}

date_default_timezone_set('Asia/Shanghai');
// require __DIR__'/phar://swoole.phar';
Swoole\Config::$debug = true;
Swoole\Error::$echo_html = false;

$AppSvr = new WebSocket();
$AppSvr->loadSetting(__DIR__ . "/swoole.ini"); // 加载配置文件
$AppSvr->setLogger(new \Swoole\Log\EchoLog(true)); // Logger


$enable_ssl = true;
$server = Swoole\Network\Server::autoCreate('0.0.0.0', 9988, $enable_ssl);
$server->setProtocol($AppSvr);
//$server->killProcessByName('swoole');
$server->daemonize(); // 作为守护进程
$server->run(array(
    'worker_num' => 60,
//     'task_worker_num'=>6,
    'log_file' => '/home/wwwroot/lnmp_nginx1.12/domain/gameserver/log/log_swoole.log',
   
    // 'ssl_key_file' => '/home/wwwroot/lnmp_nginx1.12/domain/gameserver/ssl/214302382440728.key',
    // 'ssl_cert_file' => '/home/wwwroot/lnmp_nginx1.12/domain/gameserver/ssl/214302382440728.pem',
    //'max_request' => 1000,
    'heartbeat_idle_time' => 60,
)
// 'ipc_mode' => 2,
// 'heartbeat_check_interval' => 40,
// 'heartbeat_idle_time' => 60,
);
