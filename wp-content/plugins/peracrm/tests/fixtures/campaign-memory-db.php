<?php
/** Synthetic wpdb substitute. Does NOT prove SQL, locking, indexes or multisite WP behavior. */
class Campaign_Memory_DB
{
    public $prefix = 'wp_1_';
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
            return $this->missing_unique ? [] : [['Non_unique' => 0, 'Key_name' => 'mailbox', 'Column_name' => 'normalized_email', 'Sub_part' => null]];
        }
        return [];
    }
    public function get_var($sql) {
        if (str_contains($sql, 'SELECT disposition')) { return $GLOBALS['campaign_disposition']; }
        return 'closed_reason';
    }
}
