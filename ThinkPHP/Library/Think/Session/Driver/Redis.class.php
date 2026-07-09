<?php
namespace Think\Session\Driver;

class Redis
{
    protected $lifeTime    = 3600;
    protected $sessionName = '';
    protected $handle      = null;
    protected $options     = array(
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'timeout'    => 1,
        'persistent' => 0,
        'auth'       => '',
        'database'   => 0,
    );

    /**
     * 打开Session
     * @access public
     * @param string $savePath
     * @param mixed $sessName
     */
    public function open($savePath, $sessName)
    {
        $this->lifeTime    = C('SESSION_EXPIRE') ? C('SESSION_EXPIRE') : $this->lifeTime;
        $this->sessionName = 'PHPREDIS_SESSION:' . $sessName . ($sessName ? ':' : '');
        $this->options['timeout']    = C('SESSION_TIMEOUT') ? C('SESSION_TIMEOUT') : $this->options['timeout'];
        $this->options['persistent'] = C('SESSION_PERSISTENT') ? C('SESSION_PERSISTENT') : $this->options['persistent'];
        $this->options['host']       = C('REDIS_HOST') ? C('REDIS_HOST') : $this->options['host'];
        $this->options['port']       = C('REDIS_PORT') ? C('REDIS_PORT') : $this->options['port'];
        $this->options['auth']       = C('REDIS_PASSWORD') ? C('REDIS_PASSWORD') : $this->options['auth'];
        $this->options['database']   = C('REDIS_DB') ? C('REDIS_DB') : $this->options['database'];

        $this->handle = new \Redis();
        if ($this->options['persistent']) {
            $this->handle->pconnect($this->options['host'], $this->options['port'], $this->options['timeout']);
        } else {
            $this->handle->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        }
        if (!empty($this->options['auth'])) {
            $this->handle->auth($this->options['auth']);
        }
        if ($this->options['database'] > 0) {
            $this->handle->select($this->options['database']);
        }
        return true;
    }

    /**
     * 关闭Session
     * @access public
     */
    public function close()
    {
        $this->gc(ini_get('session.gc_maxlifetime'));
        $this->handle->close();
        $this->handle = null;
        return true;
    }

    /**
     * 读取Session
     * @access public
     * @param string $sessID
     */
    public function read($sessID)
    {
        $data = $this->handle->get($this->sessionName . $sessID);
        return $data ?: '';
    }

    /**
     * 写入Session
     * @access public
     * @param string $sessID
     * @param String $sessData
     */
    public function write($sessID, $sessData)
    {
        if ($this->lifeTime > 0) {
            return $this->handle->setex($this->sessionName . $sessID, $this->lifeTime, $sessData);
        } else {
            return $this->handle->set($this->sessionName . $sessID, $sessData);
        }
    }

    /**
     * 删除Session
     * @access public
     * @param string $sessID
     */
    public function destroy($sessID)
    {
        return $this->handle->delete($this->sessionName . $sessID) >= 0;
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param string $sessMaxLifeTime
     */
    public function gc($sessMaxLifeTime)
    {
        return true;
    }
}
