<?php

class MDFA_Token_Estimator {

	public static function estimate( string $markdown ): int {
		return (int) ceil( mb_strlen( $markdown, 'UTF-8' ) / 4 );
	}
}
