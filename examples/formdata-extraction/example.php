<?php
	include ( '../../PdfToText.phpclass' ) ;

	if  ( php_sapi_name ( )  !=  'cli' )
		echo ( "<pre>" ) ;


	echo "Form data extraction using an XML definition file (sample.pdf) :\n" ;
	$pdf	=  new PdfToText ( 'sample.pdf' ) ;

	if  ( $pdf -> HasFormData ( ) )
		var_dump ( $pdf -> GetFormData ( 'sample.xml' ) ) ;

	echo "\n" ;

	echo "Form data extraction WITHOUT using an XML definition file :\n" ;
	$pdf	=  new PdfToText ( 'sample.pdf' ) ;

	if  ( $pdf -> HasFormData ( ) )
		var_dump ( $pdf -> GetFormData ( ) ) ;

	$w9  = $pdf -> GetFormData ( ) ;
