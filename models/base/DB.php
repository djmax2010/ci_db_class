<?php
require_once 'DB_Base.php';

class DB extends CI_Model{

    public static $container;

    public static function builder($link){
        if (empty($link)) {
            throw new Exception("builder is empty params");
        }
        $arr = explode('.', $link);
        if (empty($arr[0]) || empty($arr[1])) {
            throw new Exception("the builder's params format is error");
        }
        if (!isset(self::$container[$link])) {
            self::$container[$link] = new DB_Base($arr[0], $arr[1]);
        }
        return self::$container[$link];
    }
}