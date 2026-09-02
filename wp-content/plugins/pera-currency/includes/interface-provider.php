<?php

interface Pera_Currency_Provider_Interface {
	/** Fetch and return a complete, normalized USD snapshot or WP_Error. */
	public function fetch_rates();
}
