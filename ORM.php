<?php
namespace HKP;

defined('ORM_LOG_FILE') || define('ORM_LOG_FILE', '/tmp/hk_orm.log');
defined('DSN') || define('DSN', json_encode([
    'rw' => [
        ['mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '', 't_'],
    ],
    'r'  => [
        ['mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '', 't_'],
    ]
]));

class ORM extends \ArrayIterator
{
    private $fields = '*';
    private $whereData;
    private $group = '';
    private $order = '';
    private $sql = '';

    protected $tableName = '';
    protected $pkId = 'id';
    protected $pkData = 0;
    protected $params = [];
    protected $data = [];

    static private $prefix = '';

    /**
     * @var \PDO
     */
    static private $pdo_r = null;
    /**
     * @var \PDO
     */
    static private $pdo_rw = null;

    /**
     * @param null $data
     *
     * @return ORM
     */
    static public function init($data = null)
    {
        return new self($data);
    }

    public function __construct($data = null)
    {
        if (is_string($data)) $this->tableName = $data;
        elseif (is_array($data)) $this->data = $data;
        $this->dbR();
    }

    public function clear()
    {
        $this->fields = '*';
        $this->whereData = null;
        $this->group = '';
        $this->order = '';
        $this->params = [];
        $this->data = [];
        $this->pkData = 0;
        return $this;
    }

    public function __get($key)
    {
        return $this->data[$key];
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function offsetGet($index)
    {
        return $this->data[$index];
    }

    public function offsetSet($index, $newval)
    {
        $this->data[$index] = $newval;
    }

    ##### set #####

    public function fields($string)
    {
        $this->fields = $string;
        return $this;
    }

    public function where($where)
    {
        $this->whereData = $where;
        return $this;
    }

    public function group($string)
    {
        $this->group = $string;
        return $this;
    }

    public function order($string)
    {
        $this->order = $string;
        return $this;
    }

    ##### query #####

    public function find($id = null)
    {
        if ($id) {
            $this->clear();
            $this->pkData = $id;
            $this->whereData = [$this->pkId => $id];
        }
        $this->sql = "SELECT {$this->fields} FROM `" . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $this->sql .= " WHERE " . $this->getWhere();
        $this->sql .= ' LIMIT 0, 1';
        $st = $this->run();
        return $st->fetch();
    }

    public function all($start = 0, $limit = 20)
    {
        $sql = "SELECT {$this->fields} FROM `" . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        if ($this->group) $sql .= " GROUP BY {$this->group}";
        if ($this->order) $sql .= " ORDER BY {$this->order}";
        if ($limit != 0) $sql .= " LIMIT {$start},{$limit}";
        $this->sql = $sql;
        return $this->run();
    }

    public function add($data = null)
    {
        if ($data) $this->data = $data;
        $this->params = [];

        $this->dbRw();
        $keys = array_keys($this->data);
        $values = [];
        foreach ($this->data as $k => $v) {
            $values[":{$k}"] = $v;
            $this->params[$k] = $v;
        }
        $this->sql = 'INSERT INTO `' . self::$prefix . $this->tableName . '` (`' . implode('`,`', $keys) . '`) VALUES (:' . implode(',:', $keys) . ')';
        if ($this->run() === false) return false;
        $this->pkData = self::$pdo_rw->lastInsertId();
        return $this->pkData;
    }

    public function save($data = null)
    {
        if ($data) $this->data = $data;

        $this->dbRw();
        $values = [];
        foreach ($this->data as $k => $v) {
            if ($k == $this->pkId) $this->pkData = $v;
            $values[] = "`{$k}`=:{$k}";
        }
        $sql = 'UPDATE `' . self::$prefix . $this->tableName . '` SET ' . implode(',', $values);
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere(true);
        $this->sql = $sql;
        return $this->run(true)->rowCount();
    }

    public function remove()
    {
        $this->dbRw();
        $sql = 'DELETE FROM `' . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        $this->sql = $sql;
        return $this->run()->rowCount();
    }

    public function total()
    {
        $sql = "SELECT COUNT(0) AS `total` FROM `" . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        if ($this->group) $sql .= " GROUP BY {$this->group}";
        $this->sql = $sql;
        $st = $this->run();
        return $st->fetch()['total'];
    }

    ##### other #####

    public function quote($v)
    {
        return self::$pdo_r->quote($v);
    }

    public function getError()
    {
        return ['no' => self::$pdo_r->errorCode(), 'data' => self::$pdo_r->errorInfo()];
    }

    static public function beginTransaction()
    {
        $orm = self::init();
        $orm->dbRw();
        return self::$pdo_rw->beginTransaction();
    }

    static public function commit()
    {
        return self::$pdo_rw->commit();
    }

    static public function rollBack()
    {
        return self::$pdo_rw->rollBack();
    }

    ##### private #####

    /**
     * @param bool|false $isUpdate
     *
     * @return \PDOStatement
     */
    private function run($isUpdate = false)
    {
        if ($isUpdate) $this->params = array_merge($this->params, $this->data);
        $this->debug($isUpdate);
        $st = self::$pdo_r->prepare($this->sql);
        $st->execute($this->params);
        return $st;
    }

    static public function query($sql)
    {
        (new self())->dbRw();
        return self::$pdo_r->query($sql);
    }

    public function debug($isUpdate = false)
    {
        if (!file_exists(ORM_LOG_FILE)) {
            touch(ORM_LOG_FILE);
            chmod(ORM_LOG_FILE, 0777);
        }
        if (!is_writable(ORM_LOG_FILE)) {
            return $this;
        }

        if ($isUpdate) $this->params = array_merge($this->params, $this->data);
        $RW = self::$pdo_rw == null ? '[R] ' : '[W] ';
        file_put_contents(ORM_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $RW . $this->sql . '; | ' . json_encode($this->params) . "\n", FILE_APPEND);
        return $this;
    }

    private function getWhere($isUpdate = false)
    {
        $this->params = [];
        if (is_array($this->whereData)) {

            // 自定义SQL＋自定义参数
            if (isset($this->whereData['where']) && isset($this->whereData['args'])) {
                $this->params = $this->whereData['args'];
                return $this->whereData['where'];
            }

            // 常用方法
            $keys = [];
            foreach ($this->whereData as $k => $v) {
                $_k = $k;
                if (isset($this->data[$k]) && $isUpdate) $_k = '_' . $k;

                $keys[] = "`{$k}`=:{$_k}";
                $this->params[$_k] = $v;
            }
            return implode(' AND ', $keys);
        } elseif (is_string($this->whereData)) {
            if (stripos($this->whereData, '`id`=') === false && $this->pkData > 0) {
                $this->params['_' . $this->pkId] = $this->pkData;
                return $this->whereData . " AND `{$this->pkId}`=:_{$this->pkId}";
            }
            return $this->whereData;
        }
        return '';
    }

    private function dbRw()
    {
        $dsn = json_decode(DSN, 1);
        $dsn = $dsn['rw'][rand(0, count($dsn['rw']) - 1)];
        self::$prefix = $dsn[3];
        if (self::$pdo_rw) return;
        self::$pdo_rw = new \PDO($dsn[0], $dsn[1], $dsn[2],
            [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
        self::$pdo_r = self::$pdo_rw;
    }

    private function dbR()
    {
        $dsn = json_decode(DSN, 1);
        $dsn = $dsn['r'][rand(0, count($dsn['r']) - 1)];
        self::$prefix = $dsn[3];
        if (self::$pdo_rw) return;
        if (self::$pdo_r) return;
        self::$pdo_r = new \PDO($dsn[0], $dsn[1], $dsn[2],
            [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
    }

    static public function mk()
    {
        $out = '';
        (new self())->dbRw();
        $db = self::$pdo_rw->query('select database() as db')->fetch();
        $tables = self::$pdo_rw->query('SHOW TABLES;')->fetchAll();

        $out .= "<?php\n";
        $pkId = 'id';
        foreach ($tables as $table) {
            $tableName = $table['Tables_in_' . $db['db']];
            $_tableName = substr($tableName, strlen(self::$prefix));

            $desc = self::$pdo_rw->query('DESC ' . $tableName)->fetchAll();
            $keys = '';
            foreach ($desc as $row) {
                $key = $row['Field'];
                if ($row['Key'] == 'PRI') $pkId = $key;
                $_key = implode('', explode(' ', ucwords(str_replace('_', ' ', $key))));
                $keys .= <<<_

    /**
     * @return mixed
     */
    public function get{$_key}(){return \$this->data['{$key}'];}
    /**
     * @param mixed \$id
     */
    public function set{$_key}(\$value){\$this->data['{$key}'] = \$value;}

_;
            }

            $clsTpl = <<<_

class {$_tableName}_orm extends \HKP\ORM{
    protected \$tableName = '{$_tableName}';
    protected \$pk = '\${$pkId}';
{$keys}

}
_;

            $out .= $clsTpl;
        }

        return $out;
    }
}
