<?php
/***
 * Wap组 
 * Summary:Redis 操作
 * Redis 分布式锁
 * 避免
 * @author: chengql 2014/11/12
 */
class WapRedisDistributedLock {

    //锁的超时时间
    const TIMEOUT = 10;

    const SLEEP = 1000000;

    /**
     * 当前锁的过期时间
     * @var int
     */
    protected static $expire;

    public static function getRedis()
    {
    	
    	require_once CODE_BASE2 . '/util/redis/RedisClient.class.php';
    	try {
	    	if( $RedisClient == null ){
	    		$RedisClient = new RedisClient( RedisConfig::$GROUP_WEBAPP  );
	    	}
    	}catch (Exception $e){
    		
    	}
	    return $RedisClient->getMasterRedis('wapmsc');
    }

    /**
     * Gets a lock or waits for it to become available
     * 获得锁，如果锁被占用，阻塞，直到获得锁或者超时
     *
     * 如果$timeout参数为0，则立即返回锁。
     * 
     * @param  string    $key        
     * @param  int        $timeout    Time to wait for the key (seconds)
     * @return boolean    成功，true；失败，false
     */
    public static function lock($key, $timeout = null){
        if(!$key){
            return false;
        }
        $start = time();
        $redis = self::getRedis();
        try {
        	do{
	            self::$expire = self::timeout();
	            if($acquired = ($redis->setnx("Lock:{$key}", self::$expire))){
	                break;
	            }
	            if($acquired = (self::recover($key))){
	                break;
	            }
	            if($timeout === 0) {
	                //如果超时时间为0，即为
	                break;
	            }
	            usleep(self::SLEEP);
        	} while( is_numeric($timeout) &&  (time() < $start + $timeout) && $timeout-- );
        }catch (Exception $e){
    		
    	}
        if(!$acquired){
            //超时
            return false;
        }
        return true;
    }
 
    /**
     * Summary:释放锁
     * @param  mixed    $key    Item to lock
     */
    public static function release($key){
        if(!$key){
            return false;
        }
        $redis = self::getRedis();
        if(self::$expire > time()) {
            $redis->del("Lock:{$key}");
        }
    }
    /**
     * Summary: 超时时间
     */
    protected static function timeout(){
        return (int) (time() + self::TIMEOUT + 1);
    }
 
    /**
     * Recover an abandoned lock
     * @param  mixed    $key    Item to lock
     * @return bool    Was the lock acquired?
     */
    protected static function recover($key){
        $redis = self::getRedis();
        if(($lockTimeout = $redis->get("Lock:{$key}")) > time()) {
            //锁还没有过期
            return false;
        }
        $timeout = self::timeout();
        $currentTimeout = $redis->getset("Lock:{$key}", $timeout);
        if($currentTimeout != $lockTimeout) {
            return false;
        }
        self::$expire = $timeout;
        return true;
    }
}