<?php
namespace App;

use Swoole;
use Swoole\Controller;

class Onexit extends Swoole\Controller
{

    private $websocket;

    public function to($client_id,&$websocket)
    {
        $this->websocket = $websocket;
        
//         echo '用户退出';
        $sql2 = 'select id,room_id,client_id,position_id from cdx_user_and_rooms where client_id=' . $client_id;
        
//         echo $sql2;
        $room = $this->db->query($sql2);
        if($room){
            $room = $room->fetchall();
//             var_dump($room);
            if(!empty($room[0]['room_id'])){
                $room_now_update = 'update cdx_rooms set now=now+1 where id=' . $room[0]['room_id'];
//                 echo "\n".$room_now_update;
                $this->db->execute($room_now_update,'');
            }
            
            if(!empty($room[0]['id'])){
                $user_and_rooms_clear = 'update cdx_user_and_rooms set client_id=NULL,user_id=NULL,ip=NULL where id=' . $room[0]['id'];
//                 echo '更新座位为空：';
//                 var_dump($user_and_rooms_clear);
//                 echo "\n".$user_and_rooms_clear;
                $this->db->execute($user_and_rooms_clear,'');
            }
            
            $user_out = 'select * from cdx_user_and_rooms where room_id='.$room[0]['room_id'].' and client_id is not null';
//             echo $user_out;
            $user_out_rs = $this->db->query($user_out);
            if($user_out_rs){
                $user_out_arr = $user_out_rs->fetchall();
//                 echo '通知房间内其他用户，有用户下线：';
//                 var_dump($user_out_arr);
                foreach($user_out_arr as $val){
                    $this->_put($val['client_id'], $room[0]['position_id']);
                }                
            }
        }
    }
    
    //通知该房间所有用户，该用户退出房间
    private function _put($client_id,$position_id){
//         echo '通知该房间所有用户，该用户退出房间'; 
//         var_dump($client_id);
//         var_dump('{"event_name":"user_out","position_id":"'.$position_id.'"}');
        $this->websocket->send($client_id, '{"event_name":"user_out","position_id":"'.$position_id.'"}');
    }
}
