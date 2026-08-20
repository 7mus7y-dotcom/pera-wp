<?php
/** Standalone post_content source-echo retry tests: php tests/source-echo-retry-test.php */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $value ) ); }
function get_option( $key, $default = false ) { return $default; }
function do_action() {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag ? $GLOBALS['echo_test_provider'] : $value; }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class Echo_Test_Provider implements Pera_ML_Provider_Interface {
	public $responses;
	public $calls = array();
	public function __construct( array $responses ) { $this->responses = $responses; }
	public function id() { return 'echo-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'instructions' => $context['instructions'] );
		return array_shift( $this->responses );
	}
}
final class Echo_Test_Storage {
	public $puts = array();
	public function put() { $this->puts[] = func_get_args(); return true; }
}

$source = "<ul>\n  <li>Title deed and ownership information;</li>\n  <li>Whether the seller and transaction structure are suitable for citizenship;</li>\n  <li>Whether the property has previously been used for a citizenship application in a way that affects eligibility;</li>\n  <li>Planning, occupancy and property-specific documentation where relevant;</li>\n  <li>Whether the proposed price is likely to be supported by the official valuation; and</li>\n  <li>Whether the payment route can be documented correctly.</li>\n</ul>";
$registry = new class { public function get( $language ) { return array( 'name' => $language, 'source' => false ); } };

foreach ( array(
	'zh' => array(
		array( 'Title deed and ownership information; 产权证及所有权信息;', '卖方和交易结构是否适合入籍；', '该房产此前是否用于影响资格的入籍申请；', '相关规划、入住和房产文件；', '拟议价格是否可能得到官方估价支持；以及', '付款路径是否可正确记录。' ),
		'产权证及所有权信息；',
	),
	'ar' => array(
		array( 'Title deed and ownership information; سند الملكية ومعلوماتها؛', 'ما إذا كان البائع وهيكل المعاملة مناسبين للجنسية؛', 'ما إذا كان العقار قد استُخدم سابقاً في طلب جنسية يؤثر في الأهلية؛', 'وثائق التخطيط والإشغال والعقار ذات الصلة؛', 'ما إذا كان السعر المقترح مدعوماً بالتقييم الرسمي؛ و', 'ما إذا كان يمكن توثيق مسار الدفع بشكل صحيح.' ),
		'سند الملكية ومعلوماتها؛',
	),
) as $language => $responses ) {
	$storage = new Echo_Test_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$protected_source = $translator->protect( $source );
	$english_items = array( 'Title deed and ownership information;', 'Whether the seller and transaction structure are suitable for citizenship;', 'Whether the property has previously been used for a citizenship application in a way that affects eligibility;', 'Planning, occupancy and property-specific documentation where relevant;', 'Whether the proposed price is likely to be supported by the official valuation; and', 'Whether the payment route can be documented correctly.' );
	$initial_response = str_replace( $english_items, $responses[0], $protected_source['text'] );
	$GLOBALS['echo_test_provider'] = new Echo_Test_Provider( array( $initial_response, $responses[1] ) );
	$result = $translator->translate_and_store( 'post', 10, 'post_content', $language, $source, 'mock' );
	expect_same( false, is_wp_error( $result ), $language . ' translation succeeds after retry' );
	expect_same( 2, count( $GLOBALS['echo_test_provider']->calls ), $language . ' retries only one segment' );
	expect_same( 'Title deed and ownership information;', $GLOBALS['echo_test_provider']->calls[1]['source'], $language . ' retries exact affected text' );
	expect_same( true, false !== strpos( $GLOBALS['echo_test_provider']->calls[1]['instructions'], 'Return only the target-language translation. Do not repeat or include the English source text.' ), $language . ' strict retry instruction' );
	expect_same( true, false !== strpos( $result, $responses[1] ), $language . ' retry replaces echoed item' );
	expect_same( true, false !== strpos( $result, '<li>' . ( 'zh' === $language ? '卖方和交易结构是否适合入籍；' : 'ما إذا كان البائع وهيكل المعاملة مناسبين للجنسية؛' ) . '</li>' ), $language . ' successful sibling remains unchanged' );
	expect_same( 1, count( $storage->puts ), $language . ' stores only successful final result' );
}

$storage = new Echo_Test_Storage();
$GLOBALS['echo_test_provider'] = new Echo_Test_Provider( array( $initial_response, 'Title deed and ownership information; سند الملكية ومعلوماتها؛' ) );
$translator = new Pera_ML_Translator( $registry, $storage );
$failed = $translator->translate_and_store( 'post', 11, 'post_content', 'ar', $source, 'mock' );
expect_same( 'pera_ml_source_echo', $failed->get_error_code(), 'persistent source echo fails safely' );
expect_same( 0, count( $storage->puts ), 'persistent source echo is not stored' );

echo "Pera ML source-echo retry tests passed\n";
