<?php 
/**
<<<<<<< HEAD
 * 使用memcache实现分布式锁
 */

class MemCacheLockAdapter{

    private $_memHandler   = null; //memcache对象
    private $_allLockNames = array(); //所有的锁名
    private $_expiration   = 30; //锁过期时间
    private $_maxBlockTime = 10; //最长的阻塞时间

    const MAX_LOCK_NUM = 1000;//最大允许同时存在1000个锁，保留最近的1000个的锁，防止脚本cli模式下过大

    /**
     * @brief 
     * @param object $mcHandler memcache对象
     * @param array $params
     *   -maxBlockTime 最长的阻塞时间
     *   -expiration 锁过期时间
     */
    public function __construct($mcHandler, $params = array()) {
        $this->_memHandler = $mcHandler;

        if (isset($params['maxBlockTime'])) {
            $this->_maxBlockTime = $params['maxBlockTime'];
        }
        if (isset($params['expiration'])) {
            $this->_expiration   = $params['expiration'];
        }
    }

    /**
     * @brief 释放所有的锁
     */
    public function lockClean() {
        if (!$this->_allLockNames) return;

        foreach ($this->_allLockNames as $name => $value) {
            $this->release($name);
        }
    }
    
    private function _getKey($name){
        return 'mem_lockkey_'.$name;
    }

    /**
     * @brief 开始
     * @param string $name 需要所得key名
     * @param boolean $block 是否阻塞, true|阻塞， false|非阻塞
     */
    public function lock($name, $block = true) {
        if(!$this->_memHandler || !$name){
            return false;
        }
        $maxBlockTime = $this->_maxBlockTime;
        $key = $this->_getKey($name);
        do{ 
            $ctime = time();
            $ret = $this->_memHandler->add($key, $ctime, $this->_expiration);
            if($ret == true){
                $this->_addLockNames($name,$ctime);
                break;
            }
        }while($block && $maxBlockTime-- && !sleep(1));

        if (!$ret) {
            $this->_log('Lock failed ' . $name);
        }
        return $ret;
    }

    /**
     * @brief 增加锁脚本缓存
     * @param string $name
     * @param string $value
     */
    private function _addLockNames($name, $value = 1) {
        if (is_array($this->_allLockNames) && count($this->_allLockNames) >= self::MAX_LOCK_NUM) {
            array_shift($this->_allLockNames);
        }
        $this->_allLockNames[$name] = $value;;
    }

    /**
     * 释放锁
     */
    public function release($name){
        if(!$this->_memHandler || !$name){
            return false;
        }
        $key = $this->_getKey($name);
        $ret = $this->_memHandler->delete($key);
        if($ret == true){
            unset($this->_allLockNames[$name]);
        }
        return $ret;
    }

    /**
     * 释放所有的锁
     */
    public function __destruct(){
        self::lockClean();
    }

    /**
     * 日志记录
     * @param string $msg 错误信息
     */
    private function _log($msg) {
        if(class_exists('Logger')){
            Logger::logError($msg, 'lock.MemCache');
        }
    }
}
