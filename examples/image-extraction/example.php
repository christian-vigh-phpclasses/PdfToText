<?php
	// This example saves all images found in the 'sample.pdf' file, after having put the string
	// "Hello world" in blue color, using the largest stock font
	include ( '../../PdfToText.phpclass' ) ;

	function  output ( $message )
	   {
		if  ( php_sapi_name ( )  ==  'cli' )
			echo ( $message ) ;
		else
			echo ( nl2br ( $message ) ) ;
	    }

	$file		=  'sample' ;
	$pdf		=  new PdfToText ( "$file.pdf", PdfToText::PDFOPT_DECODE_IMAGE_DATA ) ;
	$image_count 	=  count ( $pdf -> Images ) ;
	
	if  ( $image_count )
	   {
		for  ( $i = 0 ; $i  <  $image_count ; $i ++ )
		   {
			// Get next image and generate a filename for it (there will be a file named "sample.x.jpg"
			// for each image found in file "sample.pdf")
			$img		=  $pdf -> Images [$i] ;			// This is an object of type PdfImage
			$imgindex 	=  sprintf ( "%02d", $i + 1 ) ;
			$output_image	=  "$file.$imgindex.jpg" ;
			
			// Allocate a color entry for "white". Note that the ImageResource property of every PdfImage object
			// is a real image resource that can be specified to any of the image*() Php functions
			$textcolor	=  imagecolorallocate ( $img -> ImageResource, 0, 0, 255 ) ;
			
			// Put the string "Hello world" on top of the image. 
			imagestring ( $img -> ImageResource, 5, 0, 0, "Hello world #$imgindex", $textcolor ) ;
			
			// Save the image (the default is IMG_JPG, but you can specify another IMG_* image type by specifying it
			// as the second parameter)
			$img -> SaveAs ( $output_image ) ;
			
			output ( "Generated image file \"$output_image\"" ) ;
		    }
	    }
	else
		echo "No image was found in sample file \"$file.pdf\"" ;