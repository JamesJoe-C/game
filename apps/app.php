<?php

class App
{

    private $session;
    
    // 事件名称
    // key=>value
    // 事件名称=>对应控制器
    private $event = [
        'user_login' => \App\Message::class,
        'game_ping'  => \App\Ping::class,
        'betting'   => \App\Betting::class,
        'timer'     => \App\AutoTimer::class,
        'get_money' => \App\Money::class,
    ];

    private $swoole;

    private $websocket_server;
    
    // 错误提示
    // private $msg = '{"error":"%d","msg":"%s","event_name":"%s","data":"%s"}';
    private $msg = [
        'error' => '',
        'msg' => '',
        'event_name' => '',
        'data' => []
    ];

    public function __construct(&$session, $swoole, &$websocket_server)
    {
        bcscale(2);
        $this->websocket_server = $websocket_server;
        $this->swoole = $swoole;
        $this->session = &$session;
        
        $onstart = new \App\AutoIssue(Swoole::$php);
        $onstart->go($websocket_server, 2, Swoole::$php);
    }
    
    // 事件驱动
    public function go($message)
    {
        //$message = $this->filter($message);
        if (! $this->check($message)) {
            echo '用户身份验证失败，session为：';
            var_dump($this->session);
            $this->websocket_server->close($this->session['client_id']);
            return $this->msg_return('1002', 'user verify fail', $message['event_name'], '');
        }
        
        if (empty($message['event_name'])) {
            return $this->msg_return('1001', "event_name don't null", $message['event_name'], '');
        }
        if (empty($this->event[$message['event_name']])) {
            return $this->msg_return('1003', 'event_name not found', $message['event_name'], '');
        }
        $data = null;
        
        try {
            // 路由
            $controller = new $this->event[$message['event_name']]($this->swoole, $this->session, $this->websocket_server);
            $action = 'index';
            if (! empty($message['action'])) {
                $action = $message['action'];
            }
            $data = $controller->$action($message);
            if ($data === false) {
                $error_msg = $message['event_name'];
                if(!empty($controller->error_msg)){
                    $error_msg = $controller->error_msg;
                }
                return $this->msg_return(3002, '数据错误', $message['event_name'], '');
            }
            $json = $this->msg_return(0, '', $message['event_name'], $data);
            var_dump($json);
            return $json;
        } catch (\Exception $e) {
            var_dump($e->getMessage() . $e->getTraceAsString());
            return $this->msg_return('3001', '系统错误', $message['event_name'], '');
        }
    }
    
    // 用户身份验证
    private function check($message)
    {
        echo "\n";
        echo '接收到的用户验证信息：';
        if ($this->session['user_id']) {
            return true;
        }
        
        var_dump($message);
        echo "\n";
        if (empty($message['user_token']) && empty($this->session['user_id'])) {
            return false;
        }
        if (empty($this->session['user_id']) && ! empty($message['user_token'])) {
            $check = new \App\Message($this->swoole, $this->session, $this->websocket_server);
            $user_info = $check->check(trim($message['user_token']));
//             echo 'user_info=';
//             var_dump($user_info);
            if (empty($user_info)) {
                return false;
            }
            $this->session['user_id'] = $user_info[0]['id'];
            $this->session['name'] = $user_info[0]['name'];
            return true;
        }
        
        return false;
    }
    
    // 返回交互的用户信息
    private function msg_return($error, $msg, $event_name, $data)
    {
        $this->msg = [
            'error' => $error,
            'msg' => $msg,
            'event_name' => $event_name,
            'data' => $data
        ];
        return json_encode($this->msg);
        // return sprintf($this->msg, $error, $msg, $event_name, empty($data)?'':json_encode($data));
    }
    
    // 用户输入参数过滤
    private function filter($message)
    {
        $arr = [];
        foreach ($message as $key => $val) {
            if (strlen($key) || strlen($val)) {
                $arr[$key] = $val;
            }
            // $arr[mysqli_real_escape_string($this->db,$key)] = mysqli_real_escape_string($this->db,$val);
        }
        return $arr;
    }
}