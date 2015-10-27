<?php
namespace HKP;

class ORM extends \ArrayIterator
{
    private $fields = '*';
    private $whereData;
    private $group = '';
    private $order = '';
    private $sql = '';
    protected $tableName = '';

    protected $params = [];
    protected $data = [];

    static private $prefix = '';

    /**
     * @var \PDO
     */
    static private $pdo_r;
    /**
     * @var \PDO
     */
    static private $pdo_rw;

    static public function init($data)
    {
        return new self($data);
    }

    public function __construct($data = null)
    {
        if (is_string($data)) $this->tableName = $data;
        elseif (is_array($data)) $this->data = $data;
        $this->dbR();
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

    public function find($id = null)
    {
        if ($id) {
            $this->params['id'] = $id;
            $this->sql = "SELECT {$this->fields} FROM `" . self::$prefix . $this->tableName . "` WHERE id=:id";
            $st = $this->runSql();
            $this->whereData = "`id`=:id";
        } else {
            $this->sql = "SELECT {$this->fields} FROM `" . self::$prefix . $this->tableName . '`';
            if ($this->whereData) $this->sql .= " WHERE " . $this->getWhere();
            $st = $this->runSql();
        }
        return $st->fetch();
    }

    public function all($start = 0, $limit = 20)
    {
        $sql = "SELECT {$this->fields} FROM `" . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        if ($this->group) $sql .= " GROUP BY {$this->group}";
        if ($this->order) $sql .= " ORDER BY {$this->order}";
        $sql .= " LIMIT {$start},{$limit}";
        $this->sql = $sql;
        return $this->runSql();
    }

    public function add($data = null)
    {
        if ($data) {
            $this->params = $data;
            $this->data = $data;
        }

        if (!$this->params) {
            $this->params = $this->data;
        }

        $this->dbRw();
        $keys = array_keys($this->data);
        $values = [];
        foreach ($this->data as $k => $v) {
            $values[":{$k}"] = $v;
        }
        $this->sql = 'INSERT INTO `' . self::$prefix . $this->tableName . '` (`' . implode('`,`', $keys) . '`) VALUES (:' . implode(',:', $keys) . ')';
        if ($this->runSql() === false) return false;
        return self::$pdo_rw->lastInsertId();
    }

    public function save($data = null)
    {
        if ($data) $this->data = $data;

        $this->dbRw();
        $values = [];
        foreach ($this->data as $k => $v) {
            $values[] = "`{$k}`=:{$k}";
            $params[":{$k}"] = $v;
        }
        $sql = 'UPDATE `' . self::$prefix . $this->tableName . '` SET ' . implode(',', $values);
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        $this->sql = $sql;
        return $this->runSql()->rowCount();
    }

    public function remove()
    {
        $this->dbRw();
        $sql = 'DELETE FROM `' . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        $this->sql = $sql;
        return $this->runSql()->rowCount();
    }

    public function total()
    {
        $sql = "SELECT COUNT(0) AS `total` FROM `" . self::$prefix . $this->tableName . '`';
        if ($this->whereData) $sql .= " WHERE " . $this->getWhere();
        if ($this->group) $sql .= " GROUP BY {$this->group}";
        $this->sql = $sql;
        $st = $this->runSql();
        return $st->fetch()['total'];
    }

    public function getError()
    {
        return ['no' => self::$pdo_r->errorCode(), 'data' => self::$pdo_r->errorInfo()];
    }

    public function beginTransaction()
    {
        $this->dbRw();
        return self::$pdo_rw->beginTransaction();
    }

    public function commit()
    {
        return self::$pdo_rw->commit();
    }

    public function rollBack()
    {
        return self::$pdo_rw->rollBack();
    }

    /**
     * @return \PDOStatement
     */
    private function runSql()
    {
        $params = array_merge($this->params, $this->data);
        echo "\n", $this->sql, json_encode($params), "\n";
        $st = self::$pdo_r->prepare($this->sql);
        $st->execute($params);
        return $st;
    }

    static public function query($sql)
    {
        (new self())->dbRw();
        return self::$pdo_r->query($sql);
    }

    public function debug()
    {
        echo $this->sql, "\n";
        print_r($this->params);
        echo "\n";
        return $this;
    }

    private function getWhere()
    {
        if (is_array($this->whereData)) {
            $keys = [];
            foreach ($this->whereData as $k => $v) {
                $_k = $k;
                if (isset($this->data[$k])) $_k = '_' . $k;

                $keys[] = "`{$k}`=:{$_k}";
                $this->params[$_k] = $v;
            }
            return implode(' AND ', $keys);
        } elseif (is_string($this->whereData)) {
            return $this->whereData;
        }
        return '';
    }

    /**
     * 连接可读写数据库
     */
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

    /**
     * 连接子读数据库
     */
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
        foreach ($tables as $table) {
            $tableName = $table['Tables_in_' . $db['db']];
            $_tableName = substr($tableName, strlen(self::$prefix));

            $desc = self::$pdo_rw->query('DESC ' . $tableName)->fetchAll();
            $keys = '';
            foreach ($desc as $row) {
                $key = $row['Field'];
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

class user extends \HKP\ORM{
    protected \$tableName = '{$_tableName}';
{$keys}

}
_;

            $out .= $clsTpl;
        }

        return $out;
    }
}
