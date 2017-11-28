<?php
include_once 'base/DB.php';

class User extends DB{

    public function __construct(){
        parent::__construct();
    }

    public function testQuery(){
        $this->User = DB::builder('users.mobile_user');
        $this->User->find('all', array('conditions' => ['uid' => '1']));
        $this->User->find('first', array('conditions' => ['uid' => '1']));
        $this->User->findByUid('1');
        $this->User->findByMobile('12345678901');
    }
}