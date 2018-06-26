<?php
namespace App;

use Swoole;
use Swoole\Controller;

class AutoIssue extends Swoole\Controller
{

    private $time = 60;

    private $server;

    private $game_id;
    
    // 开奖结果
    private $bet_result;

    private $user_bet_money = [];

    private $user_and_room = [];

    private $issue;

    public function go(&$server, $game_id, $swoole)
    {
        if (file_exists('/home/wwwroot/lnmp_nginx1.12/domain/gameserver/auto_issue.lock')) {
            return;
        }
        
        echo "\n" . '【期号／开奖／派奖 定时器】Run' . "\n";
        
        file_put_contents('/home/wwwroot/lnmp_nginx1.12/domain/gameserver/auto_issue.lock', date('Y-m-d H:i:s'));
        
        // 启动开奖定时器
        // $process = new \swoole_process(function ($process) use ($server) {
        // $serv->tick(1000, function ($id) {
        // var_dump($id);
        // });//swoole_timer_tick
        swoole_timer_tick(($this->time * 1000), function () use (&$server, &$swoole, $game_id) {
            echo "\n" . '----------------------【定时器内容开始运行：】---------------------------------' . "\n";
            try {
                $timer = new \App\AutoIssue($swoole);
                $timer->to_timer($server, $game_id);
            } catch (\Exception $e) {
                echo ($e->getMessage() . $e->getTraceAsString());
            }
            echo "\n-----------------------【定时器内容运行结束】--------------------------------\n";
        });
        // });
        
        // $server->addProcess($process);
    }
    
    // 开奖定时器
    public function to_timer(&$server, $game_id)
    {
        $this->server = &$server;
        $this->game_id = $game_id;
        // 开奖
        echo "\n【[开奖：】 \n";
        $this->_open();
        
        // 派奖，并发送通知
        echo "\n【派奖：】\n";
        $this->_put($server);
        
        // 生成下一期期号
        echo "\n【生成下一期：】\n";
        $this->_create_issue();
    }
    
    // 开奖算法
    private function _bet_operation()
    {
        $game_sql = 'select * from games where id=' . $this->game_id;
        $game_rs = $this->db->query($game_sql);
        //echo "\n查询游戏是否开启风控：\n";
        //var_dump($game_sql);
        
        if ($game_rs) {
            $game_arr = $game_rs->fetch();
            //echo "\n" . 'game表查询结果：' . "\n";
            //var_dump($game_arr);
            if ($game_arr['risk'] == 2) {
                return rand(1, 11);
            }
        } else {
            echo "\n查询游戏是否开始sql报错\n"; 
        }
        
        $odds_sql = 'select * from cdx_odds';
        $odds_arr = $this->db->query($odds_sql)->fetchall();
        
        $sql = 'select sum(o.paid_money) money,o.option_id from orders as o where o.game_id=' 
            . $this->game_id . ' and o.status=1 and o.issue_id=' . $this->issue . ' group by o.option_id';
        //echo $sql;
        $orders_rs = $this->db->query($sql);
        //var_dump($orders_rs);
        
        // 最优算法，哪个赔钱少，开哪个
        if ($orders_rs) {
            $orders_data = $orders_rs->fetchall();
            if (empty($orders_data)) {
                echo "\n无人下注，随机开启\n";
                return rand(1, 11);
            }
            echo "\n-------------------------------------开奖统计：---------------------------------------\n";
            var_dump($orders_data);
            echo "\n----------------------------------------end-----------------------------------------\n";
            $option_id = 0;
            $money = 0;
            $order_arr = [];
            
            foreach ($orders_data as $val) {
                $order_arr[$val['option_id']] = $val['money'];
            }
            foreach($odds_arr as $odds_val){
                if(empty($order_arr[$odds_val['id']])){
                    //当前算法没有人下注，开当前结果
                    $option_id = $odds_val['id'];
                    continue;
                }
                $temp_money = $order_arr[$odds_val['id']];
                $temp_option_id = $odds_val['id'];
                //当算法为0时赋值默认
                if($option_id==0){
                    $option_id = $temp_option_id;
                    $money = $temp_money;
                    continue;
                }
                //当前金额低于之前订单金额时，赋值为当前订单
                if($temp_money<$money){
                    $option_id = $temp_option_id;
                    $money = $temp_money;
                    continue;
                }elseif ($temp_money == $money) {
                    //当开奖类型一样时，随机选一个
                    if(rand(1,10)<6){
                        $option_id = $temp_option_id;
                        $money = $temp_money;
                    }
                    continue;
                }
            }
            
        }
        
        switch ($option_id) {
            case 1:
                return rand(7, 11);
                break;
            case 2:
                return rand(1, 5);
                break;
            case 3:
                return 6;
                break;
            
            default:
                
                echo '算法opetion值错误';
                var_dump($option_id);
                return rand(1, 11);
                //throw new \Exception('开奖结果错误，超出大小发的范围');
                
                break;
        }
    }
    
    // 开奖
    private function _open()
    {
        // 获取当前期号
        $issue = $this->_get_issue_id();
        
        $this->issue = $issue;
        
        // 开奖结果
        $rs = $this->_bet_operation();
        
        //echo '当前旗号：';
        //var_dump($issue);
        //var_dump($this->issue);
        
        $this->bet_result = $rs;
        if ($rs < 6) {
            $rs = 2;
        } elseif ($rs > 6) {
            $rs = 1;
        } elseif ($rs == 6) {
            $rs = 3;
        }
        
        $start_time = time();
        
        // echo '当前旗号：';
        // var_dump($issue);
        
        $this->rs = $rs;
        
        $end_time = time();
        
        $sql = 'update cdx_issues set rs=' . $rs . ',end_time="' . date('Y-m-d H:i:s') . '",status=1,open_time=' . ($end_time - $start_time) 
                . ',bet=' . $this->bet_result . ' where id=' . $issue;
        // echo $sql;
        
        $this->db->execute($sql, '');
    }
    
    // 派奖
    private function _put(&$server)
    {
        // 获取赔率
        $odd = $this->_get_odd();
        
        $orders_sql = 'select * from orders  where game_id=' . $this->game_id . ' and status=1 and issue_id=' . $this->issue;
        //echo '查询用户订单';
        // var_dump($orders_sql);
        $orders = $this->db->query($orders_sql);
        
        if ($orders) {
            // var_dump($odd);
            $orders_arr = $orders->fetchall();
            // echo '用户订单数据：';
            // var_dump($orders_arr);
            foreach ($orders_arr as $key => $val) {
                // 开奖结果
                $status = ($val['option_id'] == $this->rs) ? 8 : 2;
                // echo '用户是否中奖：' . $status . "\n";
                // 中奖金额
                $bet_money = 0;
                if ($status == 8) {
                    $bet_money = bcmul($val['play_money'],$odd[$val['option_id']],0);
                }
                echo "\n用户中奖金额：\n";
                var_dump($bet_money);
                var_dump($val['play_money']);
                var_dump($odd[$val['option_id']]);
                // 更新订单
                $temp_sql = 'update orders set status=' . $status . ',bet_money=' . $bet_money . ',updated_at="'.date('Y-m-d H:i:s').'" where id=' . $val['id'];
                $this->db->execute($temp_sql, '');
                
                // 更新用户金额 
                if ($status == 8) {
                    $user_sql = 'update users set money=money+' . $bet_money . ',bet_money=bet_money+' . $bet_money . ',win_num=win_num+1,updated_at="'.date('Y-m-d H:i:s').'" where id=' . $val['user_id'];
                    $this->db->execute($user_sql, '');
                }
                // 记录用户中奖金额
                $this->_user_bet_money_sum($val['user_id'], $bet_money);
            }
            
            // 发送用户中奖数据
            $this->_put_to_user($server);
        } else {
            return false;
        }
    }
    
    // 统计用户中奖数据
    private function _user_bet_money_sum($user_id, $money)
    {
        $room_id = '';
        $position_id = '';
        $client_id = '';
        if (! isset($this->user_and_room[$user_id])) {
            $sql = 'select * from cdx_user_and_rooms where user_id=' . $user_id;
            $user_rs = $this->db->query($sql);
            if (empty($user_rs)) {
                return;
            }
            $user_info = $user_rs->fetch();
            $this->user_and_room[$user_id] = $user_info;
            $room_id = $user_info['room_id'];
            $position_id = $user_info['position_id'];
            $client_id = $user_info['client_id'];
        } else {
            $room_id = $this->user_and_room[$user_id]['room_id'];
            $position_id = $this->user_and_room[$user_id]['position_id'];
            $client_id = $this->user_and_room[$user_id]['client_id'];
        }
        
        // echo '用户id：';
        // var_dump($user_id);
        // echo '中奖金额：';
        // var_dump($money);
        // echo '客户端id：';
        // var_dump($client_id);
        
        if (! isset($this->user_bet_money[$room_id][$position_id]['bet_money'])) {
            $this->user_bet_money[$room_id][$position_id]['bet_money'] = 0;
        }
        
        $this->user_bet_money[$room_id][$position_id]['bet_money'] += $money;//bcmul
        $this->user_bet_money[$room_id][$position_id]['client_id'] = $client_id;
    }
    
    // 按房间通知用户中奖
    private function _put_to_user(&$server)
    {
        //echo '用户中奖数据：';
        //var_dump($this->user_bet_money);
        
        $sql = 'select room_id from cdx_user_and_rooms where client_id is not null and user_id is not null group by room_id';
        
        $room_rs = $this->db->query($sql);
        
        $room_arr = $room_rs->fetchall();
        
        // $json = '{"event_name":"bet","position_id":%d,"bet_money":%d}';
        // foreach ($this->user_bet_money as $key1 => $val) {
        foreach ($room_arr as $val) {
            
            // 获取房间信息
            $sql = 'select * from cdx_user_and_rooms where client_id is not null and user_id is not null and room_id=' . $val['room_id'];
            
            $user_and_rooms_rs = $this->db->query($sql);
            
            $user_and_rooms_arr = $user_and_rooms_rs->fetchall();
            
            $temp_arr = [];
            foreach ($user_and_rooms_arr as $key => $value1) {
                $temp_bet_money = null;
                // $temp_bet_money = isset($val[$value1['position_id']]['bet_money'])?$val[$value1['position_id']]['bet_money']:0;
                //$temp_bet_money = ! empty($this->user_bet_money[$val['room_id']][$value1['position_id']]['bet_money']) ? $this->user_bet_money[$val['room_id']][$value1['position_id']]['bet_money'] : null;
                if(isset($this->user_bet_money[$val['room_id']][$value1['position_id']]['bet_money'])){
                    $temp_bet_money = $this->user_bet_money[$val['room_id']][$value1['position_id']]['bet_money'];
                }
                $temp_arr[] = [
                    'bet_money' => $temp_bet_money,
                    'position_id' => $value1['position_id']
                ];
            }
            foreach ($user_and_rooms_arr as $value2) {
                // echo '用户连接状态：';
                // var_dump($this->connections['client_id']);
                // if(empty($this->connections['client_id'])){
                // var_dump();
                // //客户端已断开
                // continue;
                // }
                // 通知客户端
                //echo "通知客户端：";
                //var_dump($value2);
                
                $temp_arr[0]['bet_money'] = $temp_arr[0]['bet_money']/10;
                echo "\n ---------------------------bet_result---------------------------  \n";
                var_dump($this->bet_result);
                var_dump($temp_arr);
                echo "\n ------------------------bet_result end--------------------------  \n";
                $server->send($value2['client_id'], json_encode([
                    'event_name' => 'bet',
                    'bet_result' => $this->bet_result,
                    'data' => $temp_arr
                ]));
            }
        }
    }
    
    // 获取当前玩法赔率
    private function _get_odd()
    {
        // 查询玩法赔率
        $sql_odd = 'select * from cdx_odds';
        $odd_rs = $this->db->query($sql_odd);
        $odd_arr = $odd_rs->fetchall();
        $odd = [];
        foreach ($odd_arr as $val) {
            $odd[$val['value']] = $val['odds'];
        }
        return $odd;
    }
    
    // 获取当前期号
    private function _get_issue_id()
    {
        $sql = 'select * from cdx_issues where status=0';
        $issues_rs = $this->db->query($sql);
        if ($issues_rs) {
            $issues = $issues_rs->fetchall();
            //echo '开奖旗号：';
            //var_dump($issues);
            if (isset($issues[1])) {
                //throw new \Exception('上一期未开奖');
            }
            return $issues[0]['id'];
        } else {
            throw new \Exception('期号不存在');
        }
        return;
    }
    
    // 生成期号
    private function _create_issue()
    {
        $sql = 'insert into cdx_issues(start_time) values("' . date('Y-m-d H:i:s') . '")';
        return $this->db->execute($sql, '');
    }
}