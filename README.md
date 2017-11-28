# ci_db_class
CI db 类似于 CakePHP 的查询方式
CI db select query doing like CakePHP's find function


- 使用示例：
```
    $this->Channel = DB::builder('contents.channel');
    //简单查询 findByXxx, Xxx表示你要查询数据表中的某字段
    $this->Channel->findByGid('105');
    //数据库字段是 下划线方式时：比如 is_live
    $this->Channel->findByIsLive(1);
    //查询一条记录
    $this->Channel->find('first',array('conditions'=>['gid'=>'105']));
    $this->Channel->find('first',array('conditions'=>['gid'=>'105'], 'fields'=>'count(1) AS count'));
    //复杂查询
    $this->Channel->find('all', array(
        'conditions' => array('gid' => '105'),
        'fields' => array('id','gid','is_live AS isLive'),
        'limit' => array(0, 10),
        'order' => array('id'=>'desc', 'gid'=>'desc'),
        )
    );
    //或者
    $this->Channel->find('all', array(
        'conditions' => array('gid' => '105'),
        'fields' => 'id, gid, is_live AS isLive',
        'limit' => '0, 10',
        'order' => array('id'=>'desc', 'gid'=>'desc'),
        )
    );
    //链表查询
    $this->Channel->find('all', array(
        'conditions' => array('gid'=>'105'),
        'joins' => array(
            'table' => 'channel_area',
            'type' => 'left',
            'conditions' => 'channel.id = channel_area.cid'
            ),
        'limit' => '0, 10',
        'order' => array('channel.id' => 'desc')
        )
    );
    //多表链表查询
    $this->Channel->find('all', array(
        'conditions' => array('gid'=>'105'),
        'fields' => 'channel_area.room_number, channel_area.city, channel_flv.streamname'
        'joins' => array(
                array(
                    'table' => 'channel_area',
                    'type' => 'left',
                    'conditions' => 'channel.id = channel_area.cid'
                ),
                array(
                    'table' => 'channel_flv',
                    'type' => 'left',
                    'conditions' => 'channel.id = channel_flv.cid'
                ),
            )
        )
    );
    //where in
    DB::builder('contents.area')->find('all', array(
        'conditions' => array(
            'id in' => array(1,2,3)
            )
        )
    );
    //where not in 
    DB::builder('contents.area')->find('all', array(
        'conditions' => array(
            'id not in' => array(1,2,3)
            )
        )
    );
    //group\ by
    DB::builder('contents.area')->find('all', array(
        'fields' => 'count(1) as count',
        'group' => 'pid'
        )
    );
    //自定义复杂条件
    DB::builder('admin.white_list')->find('all', array(
            'conditions' => array(
                "create_time >= 1491357266 AND (uid = 2999 OR use_name = 'xxd')"
            )
        )
    );
```