<?php
class session {
	private $data = array();
	private $ip;
	private static $config;
	private static $db;
	private static $started = false;
	public function __construct($config, $db) {
		if(!self::$started){
			self::$config = $config;
			self::$db = $db;
			$this->ip = $_SERVER['REMOTE_ADDR'];
			session_set_save_handler(
				array(&$this, 'open'),
				array(&$this, 'close'),
				array(&$this, 'read'),
				array(&$this, 'write'),
				array(&$this, 'destroy'),
				array(&$this, 'gc')
			);
			register_shutdown_function('session_write_close');
			ini_set('session.cookie_domain', self::$config['cookie_domain']);
			ini_set('session.name', self::$config['name']);
			ini_set('session.gc_maxlifetime', self::$config['maxlifetime']);
			session_start();
			self::$started = true;
		}
	}
    public function count() {
		if(!array_key_exists('count', $this->data)){
			$query = self::$db->query("SELECT id FROM ".self::$config['table']."");
			$this->data['count'] = self::$db->count($query);
		}
		return $this->data['count'];
    }
    public function open($save_path, $session_name) {
        return true;
    }
    public function close() {
		self::gc(self::$config['maxlifetime']);
        return true;
    }
    public function read($id) {
		$query = self::$db->query("
			SELECT
				data
			FROM
				".self::$config['table']."
			WHERE
				id = '".$id."' AND
				ip = '".$this->ip."' AND
                timestamp > '".(time() - self::$config['maxlifetime'])."' LIMIT 1
		");
        while ($row = self::$db->fetch_array($query)) {
			return $row['data'];
        }
        return "";
    }
    public function write($id, $data) {
		$time = time();
		self::$db->query("
			INSERT INTO 
				".self::$config['table']."
			(
				id,
				ip,
				data,
				timestamp
			)
			VALUES (
				'".$id."',
				'".$this->ip."',
				'".$data."',
				'".$time."'
			)
			ON DUPLICATE KEY UPDATE
				data = '".$data."',
				timestamp = '".$time."'
			WHERE 
				id = '".$id."' AND
				ip = '".$this->ip."'
		");
		return true;
    }
	public function destroy($id) {
		self::$db->query("
			DELETE FROM 
				".self::$config['table']."
			WHERE
				id = '".$id."'
		");
		return true;
    }
    public function gc($maxlifetime) {
		self::$db->query("
			DELETE FROM 
				".self::$config['table']."
			WHERE 
				timestamp < '".(time() - self::$config['maxlifetime'])."'
		");
    }
}
?>