<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Vocabulary {
	public function terms() {
		$terms = array(
			'Apartment' => array( 'zh' => '公寓', 'ar' => 'شقة' ), 'Villa' => array( 'zh' => '别墅', 'ar' => 'فيلا' ),
			'Commercial' => array( 'zh' => '商业地产', 'ar' => 'عقاري تجاري' ), 'For Sale' => array( 'zh' => '出售', 'ar' => 'للبيع' ),
			'For Rent' => array( 'zh' => '出租', 'ar' => 'للإيجار' ), 'Sea View' => array( 'zh' => '海景', 'ar' => 'إطلالة بحرية' ),
			'Bosphorus View' => array( 'zh' => '博斯普鲁斯海峡景观', 'ar' => 'إطلالة على البوسفور' ), 'Furnished' => array( 'zh' => '带家具', 'ar' => 'مفروش' ),
			'Unfurnished' => array( 'zh' => '不带家具', 'ar' => 'غير مفروش' ), 'Parking' => array( 'zh' => '停车场', 'ar' => 'موقف سيارات' ),
			'Swimming Pool' => array( 'zh' => '游泳池', 'ar' => 'مسبح' ), 'Citizenship Suitable' => array( 'zh' => '符合入籍条件', 'ar' => 'مناسب للجنسية' ),
			'Investment Suitable' => array( 'zh' => '适合投资', 'ar' => 'مناسب للاستثمار' ),
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
