<?php
$db['master'] = array(
    'type'       => Swoole\Database::TYPE_PDO,
    'host'       => "rm-bp1iudu534br61gys5o.mysql.rds.aliyuncs.com",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'Innodb',
    'user'       => "root",
    'passwd'     => "r59-P92-H4h-3Zy",
    'name'       => "guchuan",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => true, //MySQL长连接
    //'use_proxy'  => false,  //启动读写分离Proxy
    //'slaves'     => array(
    //    array('host' => '127.0.0.1', 'port' => '3307', 'weight' => 100,),
    //    array('host' => '127.0.0.1', 'port' => '3308', 'weight' => 99,),
    //    array('host' => '127.0.0.1', 'port' => '3309', 'weight' => 98,),
    //),
);


/*
 'DB_TYPE'   => 'mysql',                 //设置数据库类型
 // 'DB_HOST'   => '99ch.baidu.qianghb.net', //设置主机
 'DB_HOST'   => '99ch.baidu.qianghb.net', //设置主机
 'DB_NAME'   => '99ch_db',             //设置数据库名
 'DB_USER'   => '99ch_db_usr',           //设置用户名
 'DB_PWD'    => '99ch_db_usr@Pwd',       //设置密码
 'DB_PORT'   => '3306',   				//设置端口号
 'DB_PREFIX' => 'tp_',                   //设置表前缀
 */
/*
$db['slave'] = array(
    'type'       => Swoole\Database::TYPE_MYSQLi,
    'host'       => "99ch.baidu.qianghb.net",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'Innodb',
    'user'       => "99ch_db_usr",
    'passwd'     => "99ch_db_usr@Pwd",
    'name'       => "99ch_db",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => false, //MySQL长连接
    //'use_proxy'  => false,  //启动读写分离Proxy
    
    /* 'type'       => Swoole\Database::TYPE_MYSQLi,
    'host'       => "127.0.0.1",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'MyISAM',
    'user'       => "root",
    'passwd'     => "root",
    'name'       => "live",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => false, //MySQL长连接 
);
*/
return $db;