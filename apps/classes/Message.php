<?php
namespace App;

use Swoole;
use Swoole\Controller;
use App\Controller\Redis;

class Message extends Swoole\Controller
{
    // private $swoole;
    private $session;

    private $websocket;

    public function __construct($swoole, $session, &$websocket_server)
    {
        parent::__construct($swoole);
        $this->session = $session;
        $this->websocket = $websocket_server;
    }
    
    // 用户进入房间\拉取房间信息
    // 返回当前用户信息、房间内其他用户信息
    public function index($data)
    {
        $user_id = $this->session['user_id'];
        
        // echo '用户id：';
        // var_dump($user_id);
        // 获取用户信息
        $user_info = $this->_getUserinfo($user_id);
        
        var_dump($user_info);
        // echo '用户信息：';
        // var_dump($user_info);
        // 查找房间
        $room_id = $this->_get_room();
        $this->session['room_id'] = $room_id;
        // echo '房间id：';
        // var_dump($room_id);
        // 加入房间
        $ur_id = $this->_in_room($room_id, $user_info['id'], $this->session['client_id'], $this->session['ip']);
        
        // 获取房间内所有用户 
        $room_user = $this->_getRoomUser($room_id);
        $arr = [];
        
        //返回首页链接
        $arr['index_href'] = $this->_get_index_href();
        
        //分享链接
        
        // 历史中奖数据
        $arr['bet_history'] = $this->_get_history();
        
        //获取当前旗号当前桌面用户的下注信息
        $arr['betting_data'] = $this->_get_betting_data();
        
        $arr['user_data'] = [
            'position_id' => $this->session['position_id'],
            'nick_name' => $user_info['name'],
            'avadar' => $user_info['src'] ?: '/img/header.jpg',
            'bean' => bcdiv($user_info['money'],10,0)
        ];
        $arr['issue_time'] = $this->_get_issue();
        foreach ($room_user as $key => $val) {
            $this->_put($val['client_id'], $user_info['name'], $user_info['src'], $this->session['position_id']);
            $arr['player_list'][] = [
                'position_id' => $val['position_id'],
                'nick_name' => $val['name'],
                'avadar' => $val['src'] ?: '/img/header.jpg'
            ];
        }
        
        return $arr;
    }
    
    //返回首页的链接地址
    private function _get_index_href(){
        $sql = 'select * from users where id='.$this->session['user_id'];
        //echo "\n".'分享链接：';
        //var_dump($sql);
        $user_rs = $this->db->query($sql);
        //var_dump($user_rs);
        if(!empty($user_rs)){
            $user_arr = $user_rs->fetch(); 
            //var_dump($user_arr);
            //$href = 'https://cdx.bingoooo.cn?token='.$user_arr['index_token'];
            $href = 'http://api.bingoooo.cn';
            //var_dump($href);
            return $href;
        }else{
            echo "\n".'用户信息为空'."\n";
        }
        return '';
    }
    
    // 用户身份验证
    public function check(String $token)
    {
        
        $user_id = $this->redis->hGet('cookie',$token);
        
        $sql = 'select * from users where id="' . $user_id . '"';
        echo "\n用户身份验证：\n";
        //var_dump($sql);
        $token_res = $this->db->query($sql)->fetchall();
        //var_dump($token_res);
        return $token_res;
    }
    
    //获取当前房间所有用户的下注信息
    private function _get_betting_data(){
        //echo '用户下注信息：';
        //当前期号
        $issue = $this->_get_issue_();
        //房间号
        $room_id = $this->session['room_id'];
        //查询该房间所有用户
        $sql = 'select * from cdx_user_and_rooms where room_id ='.$room_id .' and user_id is not null and client_id is not null';
        //var_dump($sql);
        $betting_data_rs = $this->db->query($sql);
        $betting_data = $betting_data_rs->fetchall();
        
        //var_dump($betting_data);
        
        $data = [];
        foreach($betting_data as $val){
            $sql_betting = 'select * from orders where user_id = '.$val['user_id'].' and issue_id = '.$issue;
            //var_dump($sql_betting); 
            $user_betting_rs = $this->db->query($sql_betting);
            if($user_betting_rs){
                $user_betting_data = $user_betting_rs->fetchall();
                //echo '用户订单查询：';
                //var_dump($sql_betting);
                //var_dump($user_betting_data);
                foreach($user_betting_data as $val1){
                    $data[] = ['bet_info'=>$val1['option_id'],'position_id'=>$val['position_id'],'bet_count'=>bcdiv($val1['play_money'],10,0)];//bcmul
                }
                //echo '最终数据：';
                //var_dump($data);
            }
        }
        return $data;
    }
    
    // 获取历史中奖数据
    private function _get_history()
    {
        $sql = 'select bet from cdx_issues where status!=0 order by id desc limit 0,20';
        echo $sql;
        $rs = $this->db->query($sql);
        if ($rs) {
            $arr = $rs->fetchall();
            $return_arr = [];
            foreach ($arr as $key=>$val) {
                $return_arr[] = $val['bet'];
            }
            return $return_arr;
        }
    }
    
    // 获取房间内所有用户
    private function _getRoomUser($room_id)
    {
        $sql = 'select u.*,ur.position_id,ur.client_id from cdx_user_and_rooms as ur inner join users u on u.id=ur.user_id where ur.room_id=' 
                . $room_id . ' and u.id!=' . $this->session['user_id'];
        // echo $sql;
        // $rs = $this->db->query($sql);
        $arr = $this->db->query($sql)->fetchall();
        foreach($arr as $key=>$val){
            $temp_sql = 'select * from user_headers where id='.$val['header_id'];
            //var_dump($temp_sql);
            $temp_arr = $this->db->query($temp_sql)->fetch();
            $arr[$key]['src'] = $temp_arr['src'];
        }
        return $arr;
    }
    
    // 根据用户id获取用户信息
    private function _getUserinfo($user_id)
    {
        // var_dump($user_id);
        $sql = 'select u.id,u.name,u.money,uh.host,uh.src from users u left join user_headers as uh on u.header_id=uh.id where u.id=' . $user_id;
        echo $sql;
        $user_info = $this->db->query($sql)->fetch();
        // echo '用户信息：';
        // var_dump($user_info);
        $user_info['header_img'] = $user_info['src'];
        //echo "\n用户信息：\n";
        //var_dump($user_info);
        return $user_info;
    }
    
    // 查询有空座的房间
    private function _get_room()
    {
        $sql = 'select * from cdx_rooms where now>0';
        $room = $this->db->query($sql)->fetchall();
        // echo '房间信息:';
        // var_dump($room);
        $room_id = null;
        if (empty($room)) {
            $room_id = $this->_create_room();
            // echo '创建房间：';
            // var_dump($room_id);
        } else {
            $room_id = $room[0]['id'];
        }
        // echo '最终获取到的房间信息：';
        // var_dump($room_id);
        return $room_id;
    }
    
    // 无房间，创建房间
    private function _create_room()
    {
        // echo '创建房间:';
        $sql = 'insert into cdx_rooms(max_user) values(7)';
        $id = $this->db->execute($sql, '');
        
        for ($i = 1; $i < 8; $i ++) {
            $temp_sql = 'insert into cdx_user_and_rooms(position_id,room_id) values(' . $i . ',' . $id . ')';
            // var_dump($temp_sql);
            $this->db->execute($temp_sql, '');
        }
        
        return $id;
    }
    
    // 获取当前期号
    private function _get_issue_()
    {
        $sql = 'select * from cdx_issues where status=0';
        $issues_rs = $this->db->query($sql);
        //echo '当前旗号：';
        
        if ($issues_rs) {
            $issues = $issues_rs->fetch();
            //var_dump($issues);
            return $issues['id'];
        }
        return false;
    }
    
    // 获取当前期号的剩余开奖时间
    private function _get_issue()
    {
        $sql = 'select * from cdx_issues where status=0';
        $issues_rs = $this->db->query($sql);
        if ($issues_rs) {
            $issues = $issues_rs->fetch();
            $time = (60-(time()-strtotime($issues['start_time'])));
            return ($time<0)?60:$time;
        }
        return false;
    }
    
    // 加入房间
    private function _in_room($room_id, $user_id, $client_id, $ip = null)
    {
        $this->_clear_user_room();
        $sql = 'update cdx_rooms set now=now-1 where id=' . $room_id;
        $this->db->query($sql);
        
        $rooms_select = 'select now from cdx_rooms where id=' . $room_id;
        $rooms_select_rs = $this->db->query($rooms_select);
        $rooms_now = $rooms_select_rs->fetchall();
        
        // echo '查询房间当前剩余位置：';
        // var_dump($rooms_now);
        
        // 获取当前空座
        $user_room_id = $this->_get_position();
        // echo '获取当前空座：';
        // var_dump($user_room_id);
        $this->session['position_id'] = $user_room_id['position_id'];
        $user_room_id = $user_room_id['id'];
        
        // 占座
        $id = $this->_set_position($user_room_id);
        
        // $sql1 = "insert into cdx_user_and_rooms (position_id,client_id,user_id,room_id,ip) values(".$rooms_now[0]['now'].",'$client_id',$user_id,$room_id,'$ip')";
        // echo $sql1;
        // $id = $this->db->execute($sql1, '');
        
        return $id;
    }
    
    // 占座
    private function _set_position($user_room_id)
    {
        $sql = 'update cdx_user_and_rooms set client_id="' . $this->session['client_id'] . '",user_id=' . $this->session['user_id'] . ',ip="' . $this->session['ip'] . '" where id=' . $user_room_id;
        // echo '占座';
        // var_dump($sql);
        return $this->db->execute($sql, '');
    }
    
    // 查找空座
    private function _get_position()
    {
        $select_position = 'select * from cdx_user_and_rooms where client_id is NULL';
        // echo '查找空座：' . $select_position;
        $select_position_rs = $this->db->query($select_position);
        if ($select_position_rs) {
            
            return $select_position_rs->fetch();
        }
    }
    
    // 用户加入房间后推送加入信息
    private function _put($client_id, $user_name, $avadar, $position_id)
    {
         echo '用户加入房间，推送信息给其他用户：';
         //var_dump($client_id);
         //var_dump('{"event_name":"user_in","nick_name":"' . $user_name . '","avadar":"' . $avadar . '","position_id":"'.$position_id.'"}');
        $this->websocket->send($client_id, '{"event_name":"user_in","nick_name":"' . $user_name . '","avadar":"' . $avadar . '","position_id":"' . $position_id . '"}');
    }
    
    // 清理用户加入的房间信息（用于加入房间之前）
    private function _clear_user_room()
    {
        $sql1 = 'select * from cdx_user_and_rooms where user_id=' . $this->session['user_id'];
        $user_and_room = $this->db->query($sql1);
        
        if ($user_and_room) {
            $user_and_room_arr = $user_and_room->fetchall();
            // echo '清理用户房间信息：';
            // var_dump($user_and_room_arr);
            
            // echo '用户房间信息：';
            // var_dump($user_and_room_arr);
            foreach ($user_and_room_arr as $key => $val) {
                
                $sql2 = 'update cdx_room set now=now+1 where id=' . $val['room_id'];
                echo $this->db->execute($sql2, '') . "\n";
                
                $sql = 'update cdx_user_and_rooms set client=NULL,user_id=NULL,ip=NULL where id=' . $val['id'];
                echo $this->db->execute($sql, '') . "\n";
                
                // 关闭用户其余连接
                $this->websocket->close($val['client_id']);
            }
        }
    }
}
