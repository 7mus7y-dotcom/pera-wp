<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pera_WebP_Tools_List_Table extends WP_List_Table {
	protected $status_filter = 'all';

	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'singular' => 'webp-attachment',
				'plural'   => 'webp-attachments',
				'ajax'     => false,
			)
		);

		if ( ! empty( $args['status_filter'] ) ) {
			$this->status_filter = sanitize_key( $args['status_filter'] );
		}
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'thumbnail'  => 'Thumbnail',
			'id'         => 'Attachment ID',
			'filename'   => 'Filename',
			'mime'       => 'Mime Type',
			'uploaded'   => 'Upload Date',
			'status'     => 'WebP Status',
			'action'     => 'Action',
		);
	}

	public function get_sortable_columns() {
		return array(
			'id'       => array( 'ID', false ),
			'uploaded' => array( 'date', true ),
			'filename' => array( 'title', false ),
		);
	}

	protected function get_default_primary_column_name() {
		return 'filename';
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="attachments[]" value="' . esc_attr( (string) $item['id'] ) . '" />';
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$all_filtered_ids = Pera_WebP_Tools::get_filtered_attachment_ids( $this->status_filter, 0 );
		$items = array();
		foreach ( $all_filtered_ids as $attachment_id ) {
			$post = get_post( $attachment_id );
			if ( ! $post ) {
				continue;
			}
			$file_path = get_attached_file( $attachment_id );
			$items[]   = array(
				'id'       => $attachment_id,
				'thumbnail'=> wp_get_attachment_image( $attachment_id, array( 60, 60 ), true ),
				'filename' => $file_path ? wp_basename( $file_path ) : '(missing file)',
				'mime'     => get_post_mime_type( $attachment_id ),
				'uploaded' => mysql2date( 'Y-m-d H:i', $post->post_date ),
				'uploaded_ts' => strtotime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ),
				'status'   => Pera_WebP_Tools::get_attachment_status( $attachment_id ),
				'last_error' => (string) get_post_meta( $attachment_id, Pera_WebP_Tools::LAST_ERROR_META_KEY, true ),
			);
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'uploaded';
		$order   = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'desc';
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		$sortable_columns = array( 'id', 'uploaded', 'filename' );
		if ( ! in_array( $orderby, $sortable_columns, true ) ) {
			$orderby = 'uploaded';
		}

		usort(
			$items,
			function( $a, $b ) use ( $orderby, $order ) {
				$comparison = 0;
				if ( 'id' === $orderby ) {
					$comparison = (int) $a['id'] <=> (int) $b['id'];
				} elseif ( 'filename' === $orderby ) {
					$comparison = strnatcasecmp( (string) $a['filename'], (string) $b['filename'] );
					if ( 0 === $comparison ) {
						$comparison = (int) $a['id'] <=> (int) $b['id'];
					}
				} else {
					$comparison = (int) $a['uploaded_ts'] <=> (int) $b['uploaded_ts'];
					if ( 0 === $comparison ) {
						$comparison = (int) $a['id'] <=> (int) $b['id'];
					}
				}

				return 'asc' === $order ? $comparison : -$comparison;
			}
		);

		$total_items = count( $items );
		$page_items  = array_slice( $items, $offset, $per_page );
		foreach ( $page_items as &$item ) {
			unset( $item['uploaded_ts'] );
		}
		unset( $item );

		$this->items = $page_items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => max( 1, (int) ceil( $total_items / $per_page ) ),
			)
		);
	}

	protected function column_thumbnail( $item ) {
		return $item['thumbnail'] ? $item['thumbnail'] : '—';
	}

	protected function column_id( $item ) {
		return (string) $item['id'];
	}

	protected function column_filename( $item ) {
		return esc_html( $item['filename'] );
	}

	protected function column_mime( $item ) {
		return esc_html( $item['mime'] );
	}

	protected function column_uploaded( $item ) {
		return esc_html( $item['uploaded'] );
	}

	protected function column_status( $item ) {
		$labels = array(
			'converted' => 'Converted',
			'missing'   => 'Missing WebP',
			'error'     => 'Error/Skipped',
			'skipped'   => 'Skipped',
		);
		$status = isset( $labels[ $item['status'] ] ) ? $labels[ $item['status'] ] : $item['status'];
		$output = '<strong>' . esc_html( $status ) . '</strong>';
		if ( ! empty( $item['last_error'] ) && in_array( $item['status'], array( 'error', 'skipped' ), true ) ) {
			$output .= '<br><span class="description pera-webp-last-error">' . esc_html( $item['last_error'] ) . '</span>';
		}
		return $output;
	}

	protected function column_action( $item ) {
		if ( 'converted' === $item['status'] ) {
			return '<span class="description">Already converted</span>';
		}

		$nonce = wp_create_nonce( 'pera_webp_convert_' . $item['id'] );
		$url   = add_query_arg(
			array(
				'pera_webp_convert' => 1,
				'id'                => (int) $item['id'],
				'sizes'             => 1,
				'_wpnonce'          => $nonce,
			),
			admin_url( 'upload.php?page=pera-webp-tools&webp_status=' . $this->status_filter )
		);

		return '<a class="button button-small" href="' . esc_url( $url ) . '">Convert</a>';
	}

	protected function column_default( $item, $column_name ) {
		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( (string) $item[ $column_name ] );
		}
		return '';
	}
}
