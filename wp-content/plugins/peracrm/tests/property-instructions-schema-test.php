<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function add_action() {}
function peracrm_table($name) { return 'wp_2_' . $name; }
$definitions=[];
function dbDelta($sql) { global $definitions; $definitions[]=$sql; }
class SchemaDB {
    public $last_error=''; public $missing=false; public $engine='InnoDB';
    function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    function prepare($sql,...$args) { return [$sql,$args]; }
    function get_col($sql) {
        global $definitions;
        foreach ($definitions as $definition) {
            if (str_contains($sql, explode(' ', $definition)[2])) {
                preg_match_all('/^\s*([a-z_]+) (?:BIGINT|VARCHAR|DATETIME|LONGTEXT|TEXT)/m',$definition,$matches);
                return $this->missing ? ['id'] : $matches[1];
            }
        }
        return [];
    }
    function get_row() { return ['Engine'=>$this->engine]; }
}
$wpdb=new SchemaDB();
require dirname(__DIR__) . '/inc/property-instructions.php';
peracrm_instructions_install_schema();
if (count($definitions)!==2) { throw new Exception('Expected two additive tables'); }
foreach ($definitions as $sql) {
    if (!str_contains($sql,'ENGINE=InnoDB') || !str_contains($sql,'KEY client_') || str_contains($sql,'DROP ')) { throw new Exception('Unsafe schema'); }
}
foreach (['missing','engine'] as $failure) {
    $wpdb->missing=$failure==='missing'; $wpdb->engine=$failure==='engine' ? 'MyISAM' : 'InnoDB';
    try { peracrm_instructions_assert_schema(); throw new LogicException('Invalid schema accepted'); }
    catch (RuntimeException $expected) {}
}
echo "OK: additive instruction schema and failure verification\n";
