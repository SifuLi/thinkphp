<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Think\Session\Driver;

use Think\Db as ThinkDb;

/**
 * 数据库方式Session驱动
 * 通过框架数据库抽象层实现，支持所有框架兼容的数据库类型
 * (MySQL, PostgreSQL, SQLite, Oracle, SQL Server 等)
 *
 *    CREATE TABLE think_session (
 *      session_id varchar(255) NOT NULL,
 *      session_expire int(11) NOT NULL,
 *      session_data blob,
 *      UNIQUE KEY `session_id` (`session_id`)
 *    );
 */
class Db implements \SessionHandlerInterface
{

    /**
     * Session有效时间
     */
    protected $lifeTime = '';

    /**
     * session保存的数据库表名
     */
    protected $sessionTable = '';

    /**
     * 获取框架数据库驱动实例
     * 自动复用框架已有的数据库连接，支持分布式和读写分离
     * @access protected
     * @return \Think\Db\Driver|\Think\Db\Lite
     */
    protected function getDb()
    {
        return ThinkDb::getInstance();
    }

    /**
     * 获取session表名
     * @access protected
     * @return string
     */
    protected function getTable()
    {
        return $this->sessionTable;
    }

    /**
     * 安全转义字符串值（不含引号包裹）
     * @access protected
     * @param string $value
     * @return string
     */
    protected function escape($value)
    {
        return addslashes((string)$value);
    }

    /**
     * 打开Session
     * @access public
     * @param string $savePath
     * @param mixed $sessName
     * @return bool
     */
    public function open($savePath, $sessName): bool
    {
        $this->lifeTime     = C('SESSION_EXPIRE') ? C('SESSION_EXPIRE') : ini_get('session.gc_maxlifetime');
        $this->sessionTable = C('SESSION_TABLE') ? C('SESSION_TABLE') : C("DB_PREFIX") . "session";
        return true;
    }

    /**
     * 关闭Session
     * @access public
     * @return bool
     */
    public function close(): bool
    {
        $this->gc($this->lifeTime);
        return true;
    }

    /**
     * 读取Session
     * @access public
     * @param string $sessID
     * @return string
     */
    public function read($sessID): string|false
    {
        $table  = $this->getTable();
        $id     = $this->escape($sessID);
        $expire = time();
        try {
            $result = $this->getDb()->query(
                "SELECT session_data AS data FROM {$table} WHERE session_id = '{$id}' AND session_expire > {$expire}"
            );
            if (!empty($result) && isset($result[0]['data'])) {
                return $result[0]['data'];
            }
        } catch (\Exception $e) {
            // ignore
        }
        return "";
    }

    /**
     * 写入Session
     * @access public
     * @param string $sessID
     * @param string $sessData
     * @return bool
     */
    public function write($sessID, $sessData): bool
    {
        $db     = $this->getDb();
        $table  = $this->getTable();
        $id     = $this->escape($sessID);
        $expire = time() + $this->lifeTime;
        $data   = $this->escape($sessData);
        try {
            // 使用事务保证 DELETE+INSERT 的原子性（跨数据库兼容）
            $db->startTrans();
            $db->execute("DELETE FROM {$table} WHERE session_id = '{$id}'");
            $db->execute("INSERT INTO {$table} (session_id, session_expire, session_data) VALUES ('{$id}', {$expire}, '{$data}')");
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollback();
        }
        return false;
    }

    /**
     * 删除Session
     * @access public
     * @param string $sessID
     * @return bool
     */
    public function destroy($sessID): bool
    {
        $table = $this->getTable();
        $id    = $this->escape($sessID);
        try {
            $this->getDb()->execute("DELETE FROM {$table} WHERE session_id = '{$id}'");
            return true;
        } catch (\Exception $e) {
            // ignore
        }
        return false;
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param string $sessMaxLifeTime
     * @return int
     */
    public function gc($sessMaxLifeTime): int|false
    {
        $table  = $this->getTable();
        $expire = time();
        try {
            return $this->getDb()->execute("DELETE FROM {$table} WHERE session_expire < {$expire}");
        } catch (\Exception $e) {
            return 0;
        }
    }

}
