<?php
class DB_Base{

	private $db;
    private $db_slave;
    private $table;
    private $CI;
    private $database;
    private static $container;

    public function __construct($database, $table, $hash = NULL) {
        $this->CI =& get_instance();
        $this->table = $table;
        $this->database = $database;
        $this->db = $this->connect();
        $this->trySlave();
        if(empty($this->db_slave)){
            $this->db_slave = $this->db;
        }
    }

    private function connect() {
        $this->CI->config->load('database', true);
        $conf = @$this->CI->config->config['database'][$this->database];
        if (empty($conf)) {
            throw new Exception("CAN NOT FIND DB CONFIG [".$this->database."]");
        }
        if (!isset(self::$container[$this->database])) {
            self::$container[$this->database] = $this->CI->load->database($conf, true);
        }
        return self::$container[$this->database];
    }

    private function trySlave(){
        $database = $this->database.'_slave';
        if(!empty($this->CI->config->config['database'][$database])){
            $conf = $this->CI->config->config['database'][$database];
            if(!empty($conf)){
                if (!isset(self::$container[$database])) {
                    self::$container[$database] = $this->CI->load->database($conf, true);
                }
                $this->db_slave = self::$container[$database];
            }
        }
    }
    
    public function insert($data){
        $this->db->insert($this->table, $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id == 0) {
            $affect = $this->db->affected_rows();
            if ($affect > 0) {
                return true;
            } else {
                return false;
            }
        }
        return $insert_id;
    }

    public function update($data, $where){
        $this->db->where($where);
        return $this->db->update($this->table, $data);
    }

    public function delete($where){
        $this->db->where($where);
        return $this->db->delete($this->table);
    }

    public function query($sql){
        return $this->db->query($sql);
    }

    public function find($find_type = '', $conditions = array()){
        if (empty($this->table)) {
            throw new Exception("please load table");
        }
        $table = $this->table;
        if (!empty($conditions)) {
            if (empty($conditions['fields'])) {
                $fields = '*';
            } else {
                if (is_array($conditions['fields'])) {
                    $fields = implode(',', $conditions['fields']);
                } else if (is_string($conditions['fields'])) {
                    $fields = $conditions['fields'];
                }
            }
            $this->db_slave->select($fields)->from($table);
            if (!empty($conditions['joins']) && is_array($conditions['joins'])) {
                if (isset($conditions['joins']['table']) && !empty($conditions['joins']['conditions'])) { //说明是指链一张表
                    $type  = empty($conditions['joins']['type']) ? 'left' : $conditions['joins']['type'];
                    $table = $conditions['joins']['table'];
                    $alias = !empty($conditions['joins']['alias']) ? $conditions['joins']['alias'] : '';
                    if (!empty($alias)) {
                        $join_str = "{$table} AS {$alias}";
                    } else {
                        $join_str = "{$table}";
                    }
                    $this->db_slave->join($join_str, $conditions['joins']['conditions'], $type);
                } else if ($conditions['joins'][0]['table']) { //多表外链
                    foreach ($conditions['joins'] as $key => $val) {
                        $type  = empty($val['type']) ? 'left' : $val['type'];
                        $table = $val['table'];
                        $alias = !empty($val['alias']) ? $val['alias'] : '';
                        if (!empty($alias)) {
                            $join_str = "{$table} AS {$alias}";
                        } else {
                            $join_str = "{$table}";
                        }
                        $this->db_slave->join($join_str, $val['conditions'], $type);
                    }
                }
            }
            if (!empty($conditions['order']) && is_array($conditions['order'])) {
                foreach ($conditions['order'] as $key => $val) {
                    $this->db_slave->order_by($key, $val);
                }
            }
            if (!empty($conditions['group']) && is_string($conditions['group'])) {
                $this->db_slave->group_by($conditions['group']);
            }
            if ($find_type == 'first') {
                $this->db_slave->limit('1');
            } else if (!empty($conditions['limit']) && is_string($conditions['limit'])) {
                $limit = explode(',', $conditions['limit']);
            } else if (!empty($conditions['limit']) && is_array($conditions['limit'])) {
                $limit = $conditions['limit'];
            }
            if (!empty($limit)) {
                if (count($limit) == 1 && is_numeric($limit[0])) {
                    $this->db_slave->limit(trim($limit[0]));
                } else if (count($limit) == 2 && is_numeric($limit[0]) && is_numeric($limit[1])) {
                    $limit_start = trim($limit[0]);
                    $limit_end   = trim($limit[1]);
                    $this->db_slave->limit($limit_end, $limit_start);
                }
            }
            if (!empty($conditions['conditions']) && is_array($conditions['conditions'])) {
                foreach ($conditions['conditions'] as $key => $val) {
                    $key = trim($key);
                    if (is_numeric($key)) {
                        $this->db_slave->where($val);
                    } else {
                        $pos = strpos($key, ' ');
                        if (is_numeric($pos)) {
                            $str  = preg_replace('/(\ +)/i', ' ', $key, -1);
                            $cond = explode(' ', $str);
                            if (count($cond) != 2) {
                                continue;
                            }
                            if ($cond[1] == 'in') {
                                $this->db_slave->where_in($cond[0], $val);
                            } else {
                                $this->db_slave->where($key, $val);
                            }
                        } else {
                            $this->db_slave->where($key, $val);
                        }
                    }
                }
            }
        } else {
            $this->db_slave->select("*")->from($table);
        }
        if ($find_type == 'all') {
            return $this->db_slave->get()->result_array();
        } else if ($find_type == 'first') {
            return $this->db_slave->get()->row_array();
        }
    }

    public function page($page = 1, $pageSize = 20, $where = [], $order = [], $fields = []){
        $data = array();
        $count = $this->find('first', ['conditions' => $where, 'fields' => 'count(*) as count']);
        $count = isset($count['count']) ? $count['count'] : 0;
        $data['count'] = $count;
        $total_page = $data['count'] > 0 ? ceil($data['count'] / $pageSize) : 0;
        if ($total_page < $page) {
            $page = $total_page;
        }
        $page = $page > 0 ? (int)$page : 1;
        $page_list = $pageSize ? (int)$pageSize : 10;
        $limit = ($page - 1) * $pageSize;
        $data['list'] = $this->find('all', ['conditions' => $where, 'fields' => $fields, 'limit' => [$limit, $pageSize], 'order' => $order]);
        $data['total_page'] = (int)$total_page;
        $data['page'] = (int)$page;
        $data['page_list']  = (int)$pageSize;
        return $data;
    }

    public function __call($method, $arg_array){
        if (empty($arg_array[0])) {
            return array();
        }
        $search = $arg_array[0];
        $select_fields = '*';
        if (!empty($arg_array[1])) {
            $select_fields = $arg_array[1];
        }
        $arr = explode('findBy', $method);
        if (count($arr) < 2) {
            throw new Exception("class no method");
        }
        $field = strtolower(preg_replace('/((?<=[a-z])(?=[A-Z]))/', '_', $arr[1]));
        return $this->db_slave->select($select_fields)
            ->from($this->table)
            ->where([$field => $arg_array[0]])
            ->get()
            ->result_array();
    }
}