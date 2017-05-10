<?php
	include ( '../../PdfToText.phpclass' ) ;

	if  ( php_sapi_name ( )  !=  'cli' )
		echo ( "<pre>" ) ;


	$pdf_file	=  "sample-report.pdf" ;
	$xml_file	=  "sample-report.xml" ;
	$pdf		=  new PdfToText ( $pdf_file, PdfToText::PDFOPT_CAPTURE ) ;
	$pdf -> SetCaptures ( $xml_file ) ;
	$captures	=  $pdf -> GetCaptures ( ) ;

	echo ( "Document header title : " . $captures -> Title [0] -> Text . "\n" ) ;

	$index		=  0 ;
	foreach ( $captures -> ReportLines  as  $line )
	   {
		$columns	=  array ( ) ;

		foreach  ( $line  as  $column )
			$columns []	=  $column -> Text ;

		$index ++ ;
		echo ( "Page #" . $line -> Page .  ", Line #$index : " . trim ( implode ( ' *** ', $columns ) ) . "\n" ) ;
	    }
