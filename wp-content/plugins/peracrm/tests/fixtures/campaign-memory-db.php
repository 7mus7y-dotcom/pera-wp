<?php
/** Synthetic wpdb substitute. Does NOT prove SQL, locking, indexes or multisite WP behavior. */
class Campaign_Memory_DB
{
    public $prefix = 'wp_1_';
    public $schema_locks = [];
    public function __get($name) { return $name === 'options' ? $this->prefix . 'options' : null; }
    private function quoted($sql) { preg_match_all("/'((?:''|[^'])*)'/", $sql, $matches); return array_map(static function ($v) { return str_replace("''", "'", $v); }, $matches[1]); }
    public $last_error = '';
    public $insert_id = 0;
    public $tables = [];
    public $ddl = [];
    public $fail_insert = '';
    public $fail_schema = false;
    public $ddl_calls = 0;
    public $suppress_errors = false;
    public function suppress_errors($value) { $previous = $this->suppress_errors; $this->suppress_errors = $value; return $previous; }
    public $missing_unique = false;
    private $snapshot;
    private $sequence = 0;
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[sd]/', static function ($m) use ($arg) {
                return $m[0] === '%d' ? (string) (int) $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
            }, $sql, 1);
        }
        return $sql;
    }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function query($sql) {
        $this->last_error = '';
        if (str_contains($sql, '(option_name, option_value, autoload)')) {
            $v = $this->quoted($sql);
            $guarded = str_contains($sql, ' AS owner');
            $name = $guarded ? $v[5] : $v[3];
            $owned = ($this->schema_locks[$name] ?? null) === 1;
            if ($guarded) {
                $owned = $owned && (get_option($v[3]) === $v[4]);
            }
            if (!$owned) { return 0; }
            update_option($v[0], maybe_unserialize($v[1]));
            return 1;
        }
        if ($sql === 'START TRANSACTION') { $this->snapshot = $this->tables; return 0; }
        if ($sql === 'ROLLBACK') { $this->tables = $this->snapshot; return 0; }
        if ($sql === 'COMMIT') { return 0; }
        if (preg_match('/INSERT INTO (\w+) .*VALUES \(\'([^\']+)\', \'([^\']+)\', \'([^\']+)\'\)/s', $sql, $m)) {
            if (!isset($this->tables[$m[1]][$m[2]])) {
                $this->tables[$m[1]][$m[2]] = ['normalized_email' => $m[2], 'created_at' => $m[3], 'updated_at' => $m[4],
                    'unsubscribe' => 0, 'hard_bounce' => 0, 'complaint' => 0, 'administrative' => 0, 'version' => 1];
            }
            return 1;
        }
        if (preg_match('/UPDATE (\w+) SET (\w+) = 1,.*updated_at = \'([^\']+)\' WHERE normalized_email = \'([^\']+)\'/s', $sql, $m)) {
            $this->tables[$m[1]][$m[4]][$m[2]] = 1;
            $this->tables[$m[1]][$m[4]]['version']++;
            return 1;
        }
        throw new RuntimeException('Unhandled fixture SQL: ' . $sql);
    }
    public function insert($table, $data) {
        $this->last_error = '';
        if ($this->fail_insert && str_ends_with($table, $this->fail_insert)) { $this->last_error = 'synthetic error'; return false; }
        $this->insert_id = ++$this->sequence;
        $data['id'] = $this->insert_id;
        if (str_ends_with($table, 'peracrm_subscribers')) {
            foreach ($this->tables[$table] ?? [] as $row) {
                if ($row['normalized_email'] === $data['normalized_email']) { $this->last_error = 'duplicate'; return false; }
            }
            $data += ['source' => '', 'source_ref' => '', 'evidence_ref' => '', 'policy_version' => '',
                'confirmed_at' => null, 'recorded_at' => null, 'withdrawn_at' => null, 'preferred_language' => ''];
        }
        $this->tables[$table][$data['id']] = $data;
        return 1;
    }
    public function update($table, $data, $where) {
        foreach ($this->tables[$table] ?? [] as $id => $row) {
            if ($row['id'] === $where['id']) { $this->tables[$table][$id] = array_merge($row, $data); return 1; }
        }
        return 0;
    }
    public function delete($table, $where) { unset($this->tables[$table][$where['id']]); return 1; }
    public function get_row($sql, $format) {
        $this->last_error = '';
        if (preg_match('/SHOW TABLE STATUS WHERE Name = \'([^\']+)\'/', $sql, $m)) { return ['Engine' => 'InnoDB']; }
        if (!preg_match('/FROM (\w+)/', $sql, $m) || !preg_match('/normalized_email = \'([^\']+)\'/', $sql, $email)) {
            throw new RuntimeException('Unhandled fixture read: ' . $sql);
        }
        foreach ($this->tables[$m[1]] ?? [] as $row) {
            if ($row['normalized_email'] === $email[1]) {
                if (str_contains($sql, 'LEFT JOIN')) {
                    $x = $this->tables[$this->prefix . 'peracrm_email_suppressions'][$email[1]] ?? [];
                    return $row + array_intersect_key($x, array_flip(['unsubscribe', 'hard_bounce', 'complaint', 'administrative']))
                        + ['suppression_mailbox' => $x['normalized_email'] ?? null];
                }
                return $row;
            }
        }
        return null;
    }
    public function get_col($sql) {
        preg_match('/FROM (\w+)/', $sql, $m);
        preg_match_all('/^\s*([a-z_]+) (?:BIGINT|VARCHAR|DATETIME|LONGTEXT|TINYINT)/m', $this->ddl[$m[1]], $columns);
        return $columns[1];
    }
    public function get_results($sql, $format) {
        if (str_starts_with($sql, 'SHOW INDEX')) {
            if ($this->missing_unique) { return []; }
            preg_match('/FROM (\w+)/', $sql, $table);
            preg_match_all('/(?:PRIMARY KEY|UNIQUE KEY (\w+))\s+\(([^)]+)\)/', $this->ddl[$table[1]], $matches, PREG_SET_ORDER);
            $indexes = [];
            foreach ($matches as $match) {
                foreach (array_map('trim', explode(',', $match[2])) as $column) { $indexes[] = ['Non_unique'=>0,'Key_name'=>$match[1] ?: 'PRIMARY','Column_name'=>$column,'Sub_part'=>null]; }
            }
            return $indexes;
        }
        return [];
    }
    public function get_var($sql) {
        $v = $this->quoted($sql);
        if (str_starts_with($sql, 'SELECT option_value')) {
            $value = get_option($v[0], null);
            return $value === null ? null : maybe_serialize($value);
        }
        if (str_contains($sql, 'GET_LOCK(')) {
            if (isset($this->schema_locks[$v[0]])) { return 0; }
            $this->schema_locks[$v[0]] = 1;
            return 1;
        }
        if (str_starts_with($sql, 'SELECT COUNT(*)')) {
            return get_option($v[0]) === $v[1] && ($this->schema_locks[$v[2]] ?? null) === 1 ? 1 : 0;
        }
        if (str_contains($sql, 'RELEASE_LOCK(')) {
            $guarded = str_contains($sql, 'EXISTS(');
            $name = $guarded ? $v[2] : $v[0];
            if (($this->schema_locks[$name] ?? null) !== 1 || ($guarded && get_option($v[0]) !== $v[1])) { return 0; }
            unset($this->schema_locks[$name]);
            return 1;
        }
        if (str_contains($sql, 'SELECT disposition')) { return $GLOBALS['campaign_disposition']; }
        return 'closed_reason';
    }
}
