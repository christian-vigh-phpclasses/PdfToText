<?php


function decodeAsciiHex ( $input )
{
	$output = "";

	$isOdd = true;
	$isComment = false;

	for ( $i = 0, $codeHigh =  -1 ; $i < strlen ( $input ) && $input [ $i ] != '>' ; $i++  )
	{
		$c = $input [ $i ];

		if ( $isComment )
		{
			if ( $c == '\r' || $c == '\n' )
				$isComment = false;
			continue;
		}

		switch ( $c )
		{
			case '\0' :
			case '\t' :
			case '\r' :
			case '\f' :
			case '\n' :
			case ' ' :
				break;
			case '%' :
				$isComment = true;
				break;

			default :
				$code = hexdec ( $c );
				if ( $code === 0 && $c != '0' )
					return "";

				if ( $isOdd )
					$codeHigh = $code;
				else
					$output .= chr ( $codeHigh * 16 + $code );

				$isOdd =  !$isOdd;
				break;
		}
	}

	if ( $input [ $i ] != '>' )
		return "";

	if ( $isOdd )
		$output .= chr ( $codeHigh * 16 );

	return $output;


}


function decodeAscii85 ( $input )
{
	$output = "";

	$isComment = false;
	$ords = array ();

	for ( $i = 0, $state = 0 ; $i < strlen ( $input ) && $input [ $i ] != '~' ; $i++  )
	{
		$c = $input [ $i ];

		if ( $isComment )
		{
			if ( $c == '\r' || $c == '\n' )
				$isComment = false;
			continue;
		}

		if ( $c == '\0' || $c == '\t' || $c == '\r' || $c == '\f' || $c == '\n' || $c == ' ' )
			continue;
		if ( $c == '%' )
		{
			$isComment = true;
			continue;
		}
		if ( $c == 'z' && $state === 0 )
		{
			$output .= str_repeat ( chr ( 0 ), 4 );
			continue;
		}
		if ( $c < '!' || $c > 'u' )
			return "";

		$code = ord ( $input [ $i ] ) & 0xff;
		$ords [ $state++  ] = $code - ord ( '!' );

		if ( $state == 5 )
		{
			$state = 0;
			for ( $sum = 0, $j = 0 ; $j < 5 ; $j++  )
				$sum = $sum * 85 + $ords [ $j ];
			for ( $j = 3 ; $j >= 0 ; $j--  )
				$output .= chr ( $sum >> ( $j * 8 ) );
		}
	}
	if ( $state === 1 )
		return "";
	elseif ( $state > 1 )
	{
		for ( $i = 0, $sum = 0 ; $i < $state ; $i++  )
			$sum += ( $ords [ $i ] + ( $i == $state - 1 ) ) * pow ( 85, 4 - $i );
		for ( $i = 0 ; $i < $state - 1 ; $i++  )
			$ouput .= chr ( $sum >> ( ( 3 - $i ) * 8 ) );
	}

	return $output;


}


function decodeFlate ( $input )
{
	return @gzuncompress ( $input );


}


function getObjectOptions ( $object )
{
	$options = array ();
	if ( preg_match ( "#<<(.*)>>#ismU", $object, $options ) )
	{
		$options = explode ( "/", $options [ 1 ] );
		@array_shift ( $options );

		$o = array ();
		for ( $j = 0 ; $j < @count ( $options ) ; $j++  )
		{
			$options [ $j ] = preg_replace ( "#\s+#", " ", trim ( $options [ $j ] ) );
			if ( strpos ( $options [ $j ], " " ) !== false )
			{
				$parts = explode ( " ", $options [ $j ] );
				$o [ $parts [ 0 ] ] = $parts [ 1 ];
			}
			else
				$o [ $options [ $j ] ] = true;
		}
		$options = $o;
		unset ( $o );
	}

	return $options;


}


function getDecodedStream ( $stream, $options )
{
	$data = "";
	if ( empty ( $options [ "Filter" ] ) )
		$data = $stream;
	else
	{
		$length =  !empty ( $options [ "Length" ] ) ? $options [ "Length" ] : strlen ( $stream );
		$_stream = substr ( $stream, 0, $length );

		foreach ( $options as $key => $value )
		{
			if ( $key == "ASCIIHexDecode" )
				$_stream = decodeAsciiHex ( $_stream );
			if ( $key == "ASCII85Decode" )
				$_stream = decodeAscii85 ( $_stream );
			if ( $key == "FlateDecode" )
				$_stream = decodeFlate ( $_stream );
		}
		$data = $_stream;
	}
	return $data;


}


function getDirtyTexts ( &$texts, $textContainers )
{
	for ( $j = 0 ; $j < count ( $textContainers ) ; $j++  )
	{
		if ( preg_match_all ( "#\[(.*)\]\s*TJ#ismU", $textContainers [ $j ], $parts ) )
			$texts = array_merge ( $texts, @$parts [ 1 ] );
		elseif ( preg_match_all ( "#Td\s*(\(.*\))\s*Tj#ismU", $textContainers [ $j ], $parts ) )
			$texts = array_merge ( $texts, @$parts [ 1 ] );
	}


}


function getCharTransformations ( &$transformations, $stream )
{
	preg_match_all ( "#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $stream, $chars, PREG_SET_ORDER );
	preg_match_all ( "#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $stream, $ranges, PREG_SET_ORDER );

	for ( $j = 0 ; $j < count ( $chars ) ; $j++  )
	{
		$count = $chars [ $j ] [ 1 ];
		$current = explode ( "\n", trim ( $chars [ $j ] [ 2 ] ) );
		for ( $k = 0 ; $k < $count && $k < count ( $current ) ; $k++  )
		{
			if ( preg_match ( "#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim ( $current [ $k ] ), $map ) )
				$transformations [ str_pad ( $map [ 1 ], 4, "0" ) ] = $map [ 2 ];
		}
	}
	for ( $j = 0 ; $j < count ( $ranges ) ; $j++  )
	{
		$count = $ranges [ $j ] [ 1 ];
		$current = explode ( "\n", trim ( $ranges [ $j ] [ 2 ] ) );
		for ( $k = 0 ; $k < $count && $k < count ( $current ) ; $k++  )
		{
			if ( preg_match ( "#<([0-9a-f]{1,4})>\s+<([0-9a-f]{1,4})>\s+<([0-9a-f]{1,4})>#is", trim ( $current [ $k ] ), $map ) )
			{
				$from = hexdec ( $map [ 1 ] );
				$to = hexdec ( $map [ 2 ] );
				$_from = hexdec ( $map [ 3 ] );

				for ( $m = $from, $n = 0 ; $m <= $to ; $m++ , $n++  )
					$transformations [ sprintf ( "%04X", $m ) ] = sprintf ( "%04X", $_from + $n );
			}
			elseif ( preg_match ( "#<([0-9a-f]{1,4})>\s+<([0-9a-f]{1,4})>\s+\[(.*)\]#ismU", trim ( $current [ $k ] ), $map ) )
			{
				$from = hexdec ( $map [ 1 ] );
				$to = hexdec ( $map [ 2 ] );
				$parts = preg_split ( "#\s+#", trim ( $map [ 3 ] ) );

				for ( $m = $from, $n = 0 ; $m <= $to && $n < count ( $parts ) ; $m++ , $n++  )
					$transformations [ sprintf ( "%04X", $m ) ] = sprintf ( "%04X", hexdec ( $parts [ $n ] ) );
			}
		}
	}


}


function getTextUsingTransformations ( $texts, $transformations )
{
	$document = "";
	for ( $i = 0 ; $i < count ( $texts ) ; $i++  )
	{
		$isHex = false;
		$isPlain = false;

		$hex = "";
		$plain = "";
		for ( $j = 0 ; $j < strlen ( $texts [ $i ] ) ; $j++  )
		{
			$c = $texts [ $i ] [ $j ];
			switch ( $c )
			{
				case "<" :
					$hex = "";
					$isHex = true;
					break;
				case ">" :
					$hexs = str_split ( $hex, 4 );
					for ( $k = 0 ; $k < count ( $hexs ) ; $k++  )
					{
						$chex = str_pad ( $hexs [ $k ], 4, "0" );
						if ( isset ( $transformations [ $chex ] ) )
							$chex = $transformations [ $chex ];
						$document .= html_entity_decode ( "&#x" . $chex . ";" );
					}
					$isHex = false;
					break;
				case "(" :
					$plain = "";
					$isPlain = true;
					break;
				case ")" :
					$document .= $plain;
					$isPlain = false;
					break;
				case "\\" :
					$c2 = $texts [ $i ] [ $j + 1 ];
					if ( in_array ( $c2, array (
													"\\",
													"(",
													")"
					) ) )
						$plain .= $c2;
					elseif ( $c2 == "n" )
						$plain .= '\n';
					elseif ( $c2 == "r" )
						$plain .= '\r';
					elseif ( $c2 == "t" )
						$plain .= '\t';
					elseif ( $c2 == "b" )
						$plain .= '\b';
					elseif ( $c2 == "f" )
						$plain .= '\f';
					elseif ( $c2 >= '0' && $c2 <= '9' )
					{
						$oct = preg_replace ( "#[^0-9]#", "", substr ( $texts [ $i ], $j + 1, 3 ) );
						$j += strlen ( $oct ) - 1;
						$plain .= html_entity_decode ( "&#" . octdec ( $oct ) . ";" );
					}
					$j++ ;
					break;

				default :
					if ( $isHex )
						$hex .= $c;
					if ( $isPlain )
						$plain .= $c;
					break;
			}
		}
		$document .= "\n";
	}

	return $document;


}


function pdf2text ( $filename )
{
	$infile = @file_get_contents ( $filename, FILE_BINARY );
	if ( empty ( $infile ) )
		return "";

	$transformations = array ();
	$texts = array ();

	preg_match_all ( "#obj(.*)endobj#ismU", $infile, $objects );
	$objects = @$objects [ 1 ];

	for ( $i = 0 ; $i < count ( $objects ) ; $i++  )
	{
		$currentObject = $objects [ $i ];

		if ( preg_match ( "#stream(.*)endstream#ismU", $currentObject, $stream ) )
		{
			$stream = ltrim ( $stream [ 1 ] );

			$options = getObjectOptions ( $currentObject );
			if (  !( empty ( $options [ "Length1" ] ) && empty ( $options [ "Type" ] ) && empty ( $options [ "Subtype" ] ) ) )
				continue;

			$data = getDecodedStream ( $stream, $options );

			if ( strlen ( $data ) )
			{
				// if (preg_match_all("#BT(.*)ET#ismU", $data, $textContainers)) { // mauvais découpage sur Tel CRLF : CRLF 0143507794 CRLF
				 //var_dump($data);
				if ( preg_match_all ( "# /p <</MCID [0-9]{1,2}>> BDC (.*) EMC #ismU", $data, $textContainers ) )
				{ // Découpage en lignes
				                                                                                              // var_dump($textContainers);
					$textContainers = preg_replace ( "#BT(.*)ET#ismU", "$1", $textContainers [ 1 ] ); // Suppression des BT ... ET
					for ( $i = 0 ; $i < count ( $textContainers ) ; $i++  )
					{
						if ( ( $pos = strrpos ( $textContainers [ $i ], "TJ" ) ) !== false )
						{
							$search_length = strlen ( "TJ" );
							$textContainers [ $i ] = substr_replace ( $textContainers [ $i ], "WX", $pos, $search_length );
						}
					}
					$textContainers = preg_replace ( "#(.*)TJ(.*)#ismU", "$1 $2", $textContainers ); // Suppression des ... TJ ...
					for ( $i = 0 ; $i < count ( $textContainers ) ; $i++  )
					{
						if ( ( $pos = strrpos ( $textContainers [ $i ], "WX" ) ) !== false )
						{
							$search_length = strlen ( "WX" );
							$textContainers [ $i ] = substr_replace ( $textContainers [ $i ], "TJ", $pos, $search_length );
						}
					}
					// $textContainers = @$textContainers[1];
					// var_dump($textContainers);
					getDirtyTexts ( $texts, $textContainers );
				}
				else
					getCharTransformations ( $transformations, $data );
	print_r ( $transformations ) ;
			}
		}
	}

	return getTextUsingTransformations ( $texts, $transformations );


}
?>