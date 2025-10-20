<?php
namespace assignsubmission_remotecheck\local;

defined('MOODLE_INTERNAL') || die();

class remote_repository {
    /** @var \mysqli|null */
    private $conn = null;
    /** @var string|null */
    private $lasterror = null;

    /** @var array */
    private $override = [];


    public function last_error() {
        return $this->lasterror;
    }

    public function __construct(array $override = null) {
        $this->override = $override ?? [];

        $host = get_config('assignsubmission_remotecheck', 'dbhost') ?: 'localhost';
        $port = (int)(get_config('assignsubmission_remotecheck', 'dbport') ?: 3306);
        $dbname = get_config('assignsubmission_remotecheck', 'dbname') ?: '';
        $user = get_config('assignsubmission_remotecheck', 'dbuser') ?: '';
        $pass = get_config('assignsubmission_remotecheck', 'dbpass') ?: '';
        if (!$dbname || !$user) { return; }

        $this->conn = @new \mysqli($host, $user, $pass, $dbname, $port);
        if ($this->conn && !$this->conn->connect_error) {
            $this->conn->set_charset('utf8mb4');
        } else {
            $this->lasterror = $this->conn ? $this->conn->connect_error : 'No mysqli connection';
            $this->conn = null;
        }
    }

    public function ready() { return $this->conn !== null; }

    // private function table()      { return get_config('assignsubmission_remotecheck', 'table')      ?: 'buildings'; }
    // private function idcol()      { return get_config('assignsubmission_remotecheck', 'idcol')      ?: 'id'; }
    // private function addresscol() { return get_config('assignsubmission_remotecheck', 'addresscol') ?: 'building_address'; }
    // private function paramcol($i) { return get_config('assignsubmission_remotecheck', 'param'.$i)   ?: ('param'.$i); }
    // private function resultcol()  { return get_config('assignsubmission_remotecheck', 'resultcol')  ?: 'calculation_result'; }


    private function table() {
        if (!empty($this->override['table'])) { return $this->override['table']; }
        return get_config('assignsubmission_remotecheck', 'table') ?: 'buildings';
    }
    private function idcol()      { return $this->override['idcol']      ?? (get_config('assignsubmission_remotecheck','idcol')      ?: 'id'); }
    private function addresscol() { return $this->override['addresscol'] ?? (get_config('assignsubmission_remotecheck','addresscol') ?: 'building_address'); }
    private function paramcol($i) { return $this->override['param'.$i]   ?? (get_config('assignsubmission_remotecheck','param'.$i)    ?: ('param'.$i)); }
    private function resultcol()  { return $this->override['resultcol']  ?? (get_config('assignsubmission_remotecheck','resultcol')   ?: 'calculation_result'); }


    public function list_addresses() {
        if (!$this->ready()) { return array(); }
        $tbl=$this->table(); $addr=$this->addresscol(); $id=$this->idcol();
        $sql = "SELECT `{$id}` AS id, `{$addr}` AS address FROM `{$tbl}` ORDER BY `{$addr}`";
        $res = $this->conn->query($sql);
        $out = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) { $out[(string)$row['id']] = (string)$row['address']; }
            $res->free();
        }
        return $out;
    }

    public function get_row_by_id($rowid) {
        if (!$this->ready()) { return null; }
        $tbl=$this->table(); $idc=$this->idcol();
        $fields=array("`{$idc}` AS id", "`".$this->addresscol()."` AS address");
        for ($i=1;$i<=9;$i++) { $fields[] = "`".$this->paramcol($i)."` AS param{$i}"; }
        $fields[] = "`".$this->resultcol()."` AS calcresult";
        $sql = "SELECT ".implode(',', $fields)." FROM `{$tbl}` WHERE `{$idc}` = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { $this->lasterror = $this->conn->error; return null; }
        $stmt->bind_param('i', $rowid);
        if (!$stmt->execute()) { $this->lasterror = $stmt->error; $stmt->close(); return null; }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function create_row($data) {
        if (!$this->ready()) { $this->lasterror = 'No connection'; return false; }
        $tbl = $this->table();

        $cols = array();
        $cols[$this->addresscol()] = isset($data['address']) ? $data['address'] : '';
        for ($i=1; $i<=9; $i++) { $k='param'.$i; $cols[$this->paramcol($i)] = array_key_exists($k,$data) ? $data[$k] : null; }
        $cols[$this->resultcol()] = array_key_exists('calcresult',$data) ? $data['calcresult'] : null;

        $fields = array_keys($cols);
        $qs = array_fill(0, count($fields), '?');

        $fieldlist = array();
        foreach ($fields as $f) { $fieldlist[] = "`$f`"; }

        $sql = "INSERT INTO `{$tbl}` (".implode(',', $fieldlist).") VALUES (".implode(',', $qs).")";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { $this->lasterror = $this->conn->error; return false; }

        $types = ''; $vals = array();
        foreach ($cols as $v) {
            if ($v === null) { $types .= 's'; $vals[] = null; }
            else if (is_int($v) || is_float($v)) { $types .= 'd'; $vals[] = $v; }
            else { $types .= 's'; $vals[] = (string)$v; }
        }

        $bind = array_merge(array($types), $vals);
        $refs = array(); foreach ($bind as $k=>$v) { $refs[$k] = &$bind[$k]; }
        call_user_func_array(array($stmt,'bind_param'), $refs);

        $ok = $stmt->execute();
        if (!$ok) { $this->lasterror = $stmt->error; }
        $stmt->close();
        return (bool)$ok;
    }

    public function update_row($id, $data) {
        if (!$this->ready()) { $this->lasterror = 'No connection'; return false; }
        $tbl=$this->table(); $idc=$this->idcol();

        $cols=array();
        if (array_key_exists('address',$data)) { $cols[$this->addresscol()] = $data['address']; }
        for ($i=1; $i<=9; $i++) { $k='param'.$i; if (array_key_exists($k,$data)) { $cols[$this->paramcol($i)] = $data[$k]; } }
        if (array_key_exists('calcresult',$data)) { $cols[$this->resultcol()] = $data['calcresult']; }
        if (!$cols) { return true; }

        $sets=array(); foreach ($cols as $k=>$v){ $sets[] = "`$k` = ?"; }
        $sql = "UPDATE `{$tbl}` SET ".implode(',', $sets)." WHERE `{$idc}` = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { $this->lasterror = $this->conn->error; return false; }

        $types=''; $vals=array();
        foreach ($cols as $v) {
            if ($v === null) { $types.='s'; $vals[] = null; }
            else if (is_int($v) || is_float($v)) { $types.='d'; $vals[]=$v; }
            else { $types.='s'; $vals[]=(string)$v; }
        }
        $types.='i'; $vals[] = (int)$id;

        $bind = array_merge(array($types), $vals);
        $refs = array(); foreach ($bind as $k=>$v) { $refs[$k] = &$bind[$k]; }
        call_user_func_array(array($stmt,'bind_param'), $refs);

        $ok = $stmt->execute();
        if (!$ok) { $this->lasterror = $stmt->error; }
        $stmt->close();
        return (bool)$ok;
    }

    public function delete_row($id) {
        if (!$this->ready()) { $this->lasterror = 'No connection'; return false; }
        $tbl=$this->table(); $idc=$this->idcol();
        $sql = "DELETE FROM `{$tbl}` WHERE `{$idc}` = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { $this->lasterror = $this->conn->error; return false; }
        $id=(int)$id;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        if (!$ok) { $this->lasterror = $stmt->error; }
        $stmt->close();
        return (bool)$ok;
    }
}
