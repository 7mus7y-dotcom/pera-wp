<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Vocabulary {
	public function terms() {
		$terms = array(
			'Apartment' => array( 'zh' => '公寓', 'ar' => 'شقة', 'de' => 'Wohnung' ), 'Villa' => array( 'zh' => '别墅', 'ar' => 'فيلا', 'de' => 'Villa' ),
			'Commercial' => array( 'zh' => '商业地产', 'ar' => 'عقاري تجاري', 'de' => 'Gewerbeimmobilie' ), 'For Sale' => array( 'zh' => '出售', 'ar' => 'للبيع', 'de' => 'Zum Verkauf' ),
			'For Rent' => array( 'zh' => '出租', 'ar' => 'للإيجار', 'de' => 'Zur Miete' ), 'Sea View' => array( 'zh' => '海景', 'ar' => 'إطلالة بحرية', 'de' => 'Meerblick' ),
			'Bosphorus View' => array( 'zh' => '博斯普鲁斯海峡景观', 'ar' => 'إطلالة على البوسفور', 'de' => 'Bosporusblick' ), 'Furnished' => array( 'zh' => '带家具', 'ar' => 'مفروش', 'de' => 'Möbliert' ),
			'Unfurnished' => array( 'zh' => '不带家具', 'ar' => 'غير مفروش', 'de' => 'Unmöbliert' ), 'Parking' => array( 'zh' => '停车场', 'ar' => 'موقف سيارات', 'de' => 'Parkplatz' ),
			'Swimming Pool' => array( 'zh' => '游泳池', 'ar' => 'مسبح', 'de' => 'Pool' ), 'Citizenship Suitable' => array( 'zh' => '符合入籍条件', 'ar' => 'مناسب للجنسية', 'de' => 'Für die Staatsbürgerschaft geeignet' ),
			'Investment Suitable' => array( 'zh' => '适合投资', 'ar' => 'مناسب للاستثمار', 'de' => 'Für Investitionen geeignet' ),
		);
		return apply_filters( 'pera_ml_controlled_vocabulary', $terms );
	}
	public function translate( $value, $language ) {
		if ( 'en' === $language || '' === (string) $value ) return $value;
		$terms = $this->terms();
		if ( isset( $terms[ $value ][ $language ] ) ) return $terms[ $value ][ $language ];
		foreach ( $terms as $source => $translations ) if ( 0 === strcasecmp( $source, trim( (string) $value ) ) && isset( $translations[ $language ] ) ) return $translations[ $language ];
		return $value;
	}
}
