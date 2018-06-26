<?php
namespace App;

use Swoole;
use Swoole\Controller;

class Betting extends Swoole\Controller
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
        // 1为大，2为小，3为发
        /*
         * {bet_info:"1"，bet_count:5}
         */
//         echo '收到的数据：';
//         var_dump($data);
        $money = bcmul($data['bet_count'],10);
        //var_dump($money);
        $odd_id = $data['bet_info'];
        if (empty($money) || empty($odd_id)) {
            return false;
        }
        // 验证玩法
        $odd = $this->_get_option_id($odd_id);
        if (empty($odd)) {
            var_dump('玩法不存在');
            return ['error'=>3,'msg'=>'玩法不存在'];
        }
        
        // 验证用户剩余金额是否足够
        if (! $this->_user_money_check($money)) {
            var_dump('用户余额不足');
            return ['error'=>1,'msg'=>'用户余额不足'];
        }
        var_dump('生成订单');
        // 生成订单
        $order_id = $this->_create_order($odd['id'], $money, $odd['odds']);
//         echo '订单id：';
//         var_dump($order_id);
        if ($order_id) {
            // 扣钱
            $this->_dmoney($money);
            
            // 全房间推送玩家下注信息
            $this->_room_push($money, $odd['id']);
            
            return ['error'=>0,'msg'=>'',"money"=>$this->_get_user_money(),'position_id'=>$this->_get_user_position_id()];
        } else {
            var_dump('正在开奖');
            return ['error'=>2,'msg'=>'正在开奖'];
        }
    }
    
    // 用户下注后全房间推送
    private function _room_push($money, $option_id)
    {
        $sql = 'select room_id,position_id from cdx_user_and_rooms where user_id=' . $this->session['user_id'];
//         echo '查询用户房间信息：' . $sql;
        
        $room = $this->db->query($sql)->fetch();
        
//         var_dump($room);
        
        $sql1 = 'select * from cdx_user_and_rooms where room_id=' . $room['room_id'] . ' and client_id is not null and user_id!=' . $this->session['user_id'];
        echo $sql1;
        $room_user = $this->db->query($sql1)->fetchall();
        
        foreach ($room_user as $key => $val) {
            $this->websocket->send($val['client_id'], '{"event_name":"betting_on","position_id":' . $room['position_id'] . ',"bet_info":"' . $option_id . '","bet_count":' 
                    . bcdiv($money,10,0) . '}');
        }
    }
    
    // 创建订单
    private function _create_order($option_id, $play_money, $odds)
    {
        $issue_id = $this->_get_issue_id();
        if(!$issue_id){
            return false;
        }
        $sql = 'insert into orders(order_sn,issue_id,user_id,game_id,option_id,play_money,paid_money) ' . "values('" . $this->_create_ordersn() . "','" . $issue_id . "','" . $this->session['user_id'] . "','" . $this->session['game_id'] . "','" . $option_id . "','$play_money','" . bcmul($play_money, $odds) . "')";
        
        $order_id = $this->db->execute($sql, '');
        return $order_id;
    }
    
    //获取当前用户座位id
    private function _get_user_position_id(){
        $sql = 'select * from cdx_user_and_rooms where user_id = '.$this->session['user_id'];
        $position_id = $this->db->query($sql)->fetch();
        return $position_id['position_id'];
    }
    
    //查询用户当前金额
    private function _get_user_money(){
        $sql = 'select * from users where id='.$this->session['user_id'];
        $money = $this->db->query($sql)->fetch();
        return bcdiv($money['money'],10,0);
    }
    
    // 扣钱
    private function _dmoney($play_money)
    {
        //$money = $play_money;
        
        $user_sql = 'select * from users where id='.$this->session['user_id'];
        $user_info = $this->db->query($user_sql)->fetch();
        
        //var_dump($user_info); 
        
        $reward_money = $user_info['reward_money'];
        $rech_money   = $user_info['rech_money'];
        $com_money = $user_info['com_money'];
        $bet_money = $user_info['bet_money'];
        $cash_money = $user_info['cash_money'];
        $sql = 'update users set money=money-'. $play_money . ',play_money=play_money+' . $play_money .',updated_at="'.date('Y-m-d H:i:s').'"';
        
        
        //$play_money -= $rech_money;
        if($reward_money>=$play_money){
            $sql .= ',reward_money=reward_money-'.$play_money. ' where id=' . $this->session['user_id'];
            //echo '单独扣除奖励金';
            //var_dump($sql);
            return $this->db->execute($sql, '');
        }else{
            $play_money = $play_money - $reward_money;
            $sql .= ',reward_money = 0';
        }        
        
        if($bet_money>=$play_money){
            $sql .= ',bet_money=bet_money-'.$play_money. ' where id=' . $this->session['user_id'];
            //echo '扣除奖励金、中奖金额'; 
            //var_dump($sql);
            return $this->db->execute($sql, '');
        }else{
            $play_money = $play_money-$bet_money;
            $sql .= ', bet_money = 0';
        }
        
        //$play_money -= $rech_money;
        if($rech_money>=$play_money){
            $sql .= ',rech_money=rech_money- '.$play_money. ' where id=' . $this->session['user_id'];
            //echo '扣除奖励金、中奖金额、充值金额';
            //var_dump($play_money);
            //var_dump($sql);
            return $this->db->execute($sql, '');
        }else{
            $play_money -= $rech_money;
            $sql .= ',rech_money = 0';
        }
        
        
        $sql .= ' where id=' . $this->session['user_id'];
        //var_dump($sql);
        return $this->db->execute($sql, '');
    }
    
    // 获取当前期号
    private function _get_issue_id()
    {
        $sql = 'select * from cdx_issues where status=0';
        $issues_rs = $this->db->query($sql);
        if ($issues_rs ) {
            $issues = $issues_rs->fetch();
            if((time()-strtotime($issues['start_time']))>=57){
//                 echo "\n开始时间；\n";
//                 var_dump($issues['start_time']);
//                 var_dump(strtotime($issues['start_time']));
//                 var_dump(time()-strtotime($issues['start_time']));
                return false;
            }
            
            return $issues['id'];
        } 
        return false;
    }
    
    // 玩法id验证是否存在于数据库
    private function _get_option_id($option_id)
    {
        $sql = 'select * from cdx_odds where id=' . $option_id;
        $arr = $this->db->query($sql)->fetch();
        if (empty($arr)) {
            return null;
        }
        return $arr;
    }
    
    // 验证用户金额是否足够
    private function _user_money_check($play_money)
    {
        $sql = 'select money from users where id=' . $this->session['user_id'];
//         var_dump($sql);
//         var_dump($play_money);
        $user_info = $this->db->query($sql);
        if ($user_info) {
            $user_money = $user_info->fetch();
//             echo '用户剩余金额：';
//             var_dump($user_money);
            if ($user_money['money'] >= $play_money) {
                return true;
            }
            return false;
        }
        return false;
    }
    
    // 订单编号生成器
    private function _create_ordersn()
    {
        return 'cdx' . date('YmdHis') . '_' . rand(1000, 9999);
    }
}
