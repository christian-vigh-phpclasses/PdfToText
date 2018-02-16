<?php
/**************************************************************************************************************

    NAME
	PdfToText.phpclass

    DESCRIPTION
    	A class for extracting text from Pdf files.
	Usage is very simple : just instantiate a PdfToText object, specifying an input filename, then use the
	Text property to retrieve PDF textual contents :

		$pdf	=  new PdfToText ( 'sample.pdf' ) ;
		echo $pdf -> Text ;		// or : echo ( string ) $pdf ;

	Or :

		$pdf	=  new PdfToText ( ) ;
		// Modify any property here before loading the file ; for example :
		// $pdf -> BlockSeparator = " " ;
		$pdf -> Load ( 'sample.pdf' ) ;
		echo $pdf -> Text ;

    AUTHOR
        Christian Vigh, 04/2016.

    HISTORY
    [Version : 1.6.7]	[Date : 2017/05/31]     [Author : CV]
	. Added CID fonts
	. Changed the way CID font maps are searched and handled
	
    (...)

    [Version : 1.0]	[Date : 2016/04/16]     [Author : CV]
        Initial version.

 **************************************************************************************************************/


/*==============================================================================================================

    class PdfToTextException et al -
        Implements an exception thrown when an error is encountered while decoding PDF files.

  ==============================================================================================================*/

// PdfToText exception -
//	Base class for all other PdfToText exceptions.
class  PdfToTextException			extends  Exception 
   {
	public static	$IsObject		=  false ;
    } ;


// PdfToTextDecodingException -
//	Thrown when unexpected data is encountered while analyzing PDF contents.
class  PdfToTextDecodingException		extends  PdfToTextException 
   { 
	public function  __construct ( $message, $object_id = false )
	   {
		$text	=  "Pdf decoding error" ;

		if  ( $object_id  !==  false )
			$text	.=  " (object #$object_id)" ;

		$text	.=  " : $message" ;

		parent::__construct ( $text ) ;
	    }
    }


// PdfToTextDecryptionException -
//	Thrown when something unexpected is encountered while processing encrypted data.
class  PdfToTextDecryptionException		extends  PdfToTextException
   {
	public function  __construct ( $message, $object_id = false )
	   {
		$text	=  "Pdf decryption error" ;

		if  ( $object_id  !==  false )
			$text	.=  " (object #$object_id)" ;

		$text	.=  " : $message" ;

		parent::__construct ( $text ) ;
	    }
    }


// PdfToTextTimeoutException -
//	Thrown when the PDFOPT_ENFORCE_EXECUTION_TIME or PDFOPT_ENFORCE_GLOBAL_EXECUTION_TIME option is set, and
//	the script took longer than the allowed execution time limit.
class  PdfToTextTimeoutException		extends  PdfToTextException
   { 
	// Set to true if the reason why the max execution time was reached because of too many invocations of the Load() method
	// Set to false if the max execution time was reached by simply processing one PDF file
	public		$GlobalTimeout ;

	public function  __construct ( $message, $global, $php_setting, $class_setting )
	   {
		$text	=  "PdfToText max execution time reached " ;

		if  ( ! $global )
			$text	.=  "for one single file " ;

		$text	.=  "(php limit = {$php_setting}s, class limit = {$class_setting}s) : $message" ;

		$this -> GlobalTimeout		=  $global ;

		parent::__construct ( $text ) ;
	    }
    }


// PdfToTextFormException -
//	Thrown if the xml template passed to the GetFormData() method contains an error.
class  PdfToTextFormException		extends  PdfToTextException 
   { 
	public function  __construct ( $message )
	   {
		$text	=  "Pdf form template error" ;

		$text	.=  " : $message" ;

		parent::__construct ( $text ) ;
	    }
    }


// PdfToTextCaptureException -
//	Thrown if the xml template passed to the SetCaptures() method contains an error.
class  PdfToTextCaptureException		extends  PdfToTextException 
   { 
	public function  __construct ( $message )
	   {
		$text	=  "Pdf capture template error" ;

		$text	.=  " : $message" ;

		parent::__construct ( $text ) ;
	    }
    }



/*==============================================================================================================

        Custom error reporting functions.

  ==============================================================================================================*/
if  ( ! function_exists ( 'warning' ) )
   {
	function  warning ( $message )
	   {
		trigger_error ( $message, E_USER_WARNING ) ;
	    }
    }


if  ( ! function_exists ( 'error' ) )
   {
	function  error ( $message )
	   {
		if  ( is_string ( $message ) )
			trigger_error ( $message, E_USER_ERROR ) ;
		else if (  is_a ( $message, '\Exception' ) )
			throw $message ;
	    }
    }


/*==============================================================================================================

        Backward-compatibility issues.

  ==============================================================================================================*/

// hex2bin -
//	This function appeared only in version 5.4.0
if  ( ! function_exists ( 'hex2bin' ) )
   {
	function  hex2bin  ( $hexstring ) 
	   { 
		$length		=  strlen ( $hexstring ) ;
		$binstring	=  '' ;
		$index		=  0 ;

		while  ( $index   <  $length )
		   {
			$byte		 =  substr ( $hexstring, $index, 2 ) ;
			$ch		 =  pack ( 'H*', $byte ) ;
			$binstring	.=  $ch ;

			$index		+=  2 ;
		    }

		return ( $binstring ) ;
	    } 

    }


/*==============================================================================================================

    class PfObjectBase -
        Base class for all PDF objects defined here.

  ==============================================================================================================*/
abstract class  PdfObjectBase		// extends  Object
   {
	// Possible encoding types for streams inside objects ; "unknown" means that the object contains no stream
	const 	PDF_UNKNOWN_ENCODING 		=   0 ;		// No stream decoding type could be identified
	const 	PDF_ASCIIHEX_ENCODING 		=   1 ;		// AsciiHex encoding - not tested
	const 	PDF_ASCII85_ENCODING		=   2 ;		// Ascii85 encoding - not tested
	const 	PDF_FLATE_ENCODING		=   3 ;		// Flate/deflate encoding
	const	PDF_TEXT_ENCODING		=   4 ;		// Stream data appears in clear text - no decoding required
	const	PDF_LZW_ENCODING		=   5 ;		// Not implemented yet
	const	PDF_RLE_ENCODING		=   6 ;		// Runtime length encoding ; not implemented yet
	const	PDF_DCT_ENCODING		=   7 ;		// JPEG images
	const	PDF_CCITT_FAX_ENCODING		=   8 ;		// CCITT Fax encoding - not implemented yet
	const	PDF_JBIG2_ENCODING		=   9 ;		// JBIG2 filter encoding (black/white) - not implemented yet
	const	PDF_JPX_ENCODING		=  10 ;		// JPEG2000 encoding - not implemented yet

	// Regular expression used for recognizing references to a font (this list is far from being exhaustive, as it seems
	// that you can specify almost everything - however, trying to recognize everything would require to develop a complete
	// parser)
	protected static	$FontSpecifiers		=  '
		(/F \d+ (\.\d+)? )						| 
		(/R \d+)							| 
		(/f-\d+-\d+)							| 
		(/[CT]\d+_\d+)							| 
		(/TT \d+)							| 
		(/OPBaseFont \d+)						| 
		(/OPSUFont \d+)							| 
		(/[0-9a-zA-Z])							| 
		(/F\w+)								|
		(/[A-Za-z][A-Za-z0-9]* ( [\-+] [A-Za-z][A-Za-z0-9]* ))
		' ;

	// Maps alien Unicode characters such as special spaces, letters with ligatures to their ascii string equivalent
	protected static	$UnicodeToSimpleAscii	=  false ;


 	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Performs static initializations such as the Unicode to Ascii table.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( )
	   { 
		if  ( self::$UnicodeToSimpleAscii  ===  false )
		   {
			$charset_file			=  dirname ( __FILE__ ) . "/Maps/unicode-to-ansi.map" ;
			include ( $charset_file ) ;
			self::$UnicodeToSimpleAscii	=  ( isset ( $unicode_to_ansi ) ) ?  $unicode_to_ansi : array ( ) ;
		    }

		// parent::__construct ( ) ; 
	    }


 	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        CodePointToUtf8 - Encodes a Unicode codepoint to UTF8.
	
	    PROTOTYPE
	        $char	=  $this -> CodePointToUtf8 ( $code ) ;
	
	    DESCRIPTION
	        Encodes a Unicode codepoint to UTF8, trying to handle all possible cases.
	
	    PARAMETERS
	        $code (integer) -
	                Unicode code point to be translated.
	
	    RETURN VALUE
	        A string that contains the UTF8 bytes representing the Unicode code point.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  CodePointToUtf8 ( $code )
	   {
		if  ( $code )
		   {
			$result		=  '' ;

			while  ( $code )
			   {
				$word		=  ( $code & 0xFFFF ) ;

				if  ( ! isset ( self::$UnicodeToSimpleAscii [ $word ] ) )
				   {
					$entity		 =  "&#$word;" ;
					$result		.=  mb_convert_encoding ( $entity, 'UTF-8', 'HTML-ENTITIES' ) . $result ;
				    }
				else
					$result		.=  self::$UnicodeToSimpleAscii [ $word ] ;

				$code		 =  ( integer ) ( $code / 0xFFFF ) ;	// There is no unsigned right-shift operator in PHP...
			    }

			return ( $result ) ;
		    }
		// No translation is apparently possible : use a placeholder to signal this situation
		else
		   {
			if  ( strpos ( PdfToText::$Utf8Placeholder, '%' )   ===  false )
			   {
				return ( PdfToText::$Utf8Placeholder ) ;
			    }
			else 
				return ( sprintf ( PdfToText::$Utf8Placeholder, $code ) ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    DecodeRawName -
		Decodes a string that may contain constructs such as '#xy', where 'xy' are hex digits.

	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  DecodeRawName ( $str )
	   {
		return ( rawurldecode ( str_replace ( '#', '%', $str ) ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        GetEncodingType - Gets an object encoding type.

	    PROTOTYPE
	        $type	=  $this -> GetEncodingType ( $object_id, $object_data ) ;

	    DESCRIPTION
	        When an object is a stream, returns its encoding type.

	    PARAMETERS
		$object_id (integer) -
			PDF object number.

	        $object_data (string) -
	                Object contents.

	    RETURN VALUE
	        Returns one of the following values :

		- PdfToText::PDF_ASCIIHEX_ENCODING :
			Hexadecimal encoding of the binary values.
			Decoding algorithm was taken from the unknown contributor and not tested so far, since I
			couldn't find a PDF file with such an encoding type.

		- PdfToText::PDF_ASCII85_ENCODING :
			Obscure encoding format.
			Decoding algorithm was taken from the unknown contributor and not tested so far, since I
			couldn't find a PDF file with such an encoding type.

		- PdfToText::PDF_FLATE_ENCODING :
			gzip/deflate encoding.

		- PdfToText::PDF_TEXT_ENCODING :
			Stream data is unencoded (ie, it is pure ascii).

		- PdfToText::PDF_UNKNOWN_ENCODING :
			The object data does not specify any encoding at all. It can happen on objects that do not have
			a "stream" part.

		- PdfToText::PDF_DCT_ENCODING :
			a lossy filter based on the JPEG standard.

		The following constants are defined but not yet implemented ; an exception will be thrown if they are
		encountered somewhere in the PDF file :

		- PDF_LZW_ENCODING :
			a filter based on LZW Compression; it can use one of two groups of predictor functions for more 
			compact LZW compression : Predictor 2 from the TIFF 6.0 specification and predictors (filters) 
			from the PNG specification

		- PDF_RLE_ENCODING :
			a simple compression method for streams with repetitive data using the run-length encoding 
			algorithm and the image-specific filters.

		PDF_CCITT_FAX_ENCODING :
			a lossless bi-level (black/white) filter based on the Group 3 or Group 4 CCITT (ITU-T) fax 
			compression standard defined in ITU-T T.4 and T.6.

		PDF_JBIG2_ENCODING :
			a lossy or lossless bi-level (black/white) filter based on the JBIG2 standard, introduced in 
			PDF 1.4.

		PDF_JPX_ENCODING :
			a lossy or lossless filter based on the JPEG 2000 standard, introduced in PDF 1.5.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetEncodingType ( $object_id, $object_data )
	   {
		$status 	=  preg_match ( '# / (?P<encoding> (ASCIIHexDecode) | (AHx) | (ASCII85Decode) | (A85) | (FlateDecode) | (Fl) | (DCTDecode) | (DCT) | ' .
						                   '(LZWDecode) | (LZW) | (RunLengthDecode) | (RL) | (CCITTFaxDecode) | (CCF) | (JBIG2Decode) | (JPXDecode) ) \b #imsx', 
						$object_data, $match ) ;

		if  ( ! $status )
			return ( self::PDF_TEXT_ENCODING ) ;

		switch ( strtolower ( $match [ 'encoding' ] ) )
		    {
		    	case 	'asciihexdecode' 	:  
			case	'ahx'			:  return ( self::PDF_ASCIIHEX_ENCODING  ) ;

		    	case 	'ascii85decode' 	:  
			case	'a85'			:  return ( self::PDF_ASCII85_ENCODING   ) ;

		    	case	'flatedecode'		:  
			case	'fl'			:  return ( self::PDF_FLATE_ENCODING     ) ;

			case    'dctdecode'		:  
			case	'dct'			:  return ( self::PDF_DCT_ENCODING       ) ;

			case	'lzwdecode'		:  
			case	'lzw'			:  return ( self::PDF_LZW_ENCODING       ) ; 

			case	'ccittfaxdecode'	: 
			case	'ccf'			:

			case	'runlengthdecode'	:
			case	'rl'			:

			case	'jbig2decode'		: 

			case	'jpxdecode'		:
				if  ( PdfToText::$DEBUG  >  1 )
					warning ( "Encoding type \"{$match [ 'encoding' ]}\" not yet implemented for pdf object #$object_id." ) ;

			default				:  return ( self::PDF_UNKNOWN_ENCODING  ) ;
		     }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetObjectReferences - Gets object references from a specified construct.
	
	    PROTOTYPE
	        $status		=  $this -> GetObjectReferences ( $object_id, $object_data, $searched_string, &$object_ids ) ;
	
	    DESCRIPTION
	        Certain parameter specifications are followed by an object reference of the form :
			x 0 R
		but it can also be an array of references :
			[x1 0 R x2 0 R ... xn 0 r]
		Those kind of constructs can occur after parameters such as : /Pages, /Contents, /Kids...
		This method extracts the object references found in such a construct.
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the object to be analyzed.

		$object_data (string) -
			Object contents.

		$searched_string (string) - 
			String to be searched, that must be followed by an object or an array of object references.
			This parameter can contain constructs used in regular expressions. Note however that the '#'
			character must be escaped, since it is used as a delimiter in the regex that is applied on
			object data.

		$object_ids (array of integers) -
			Returns on output the ids of the pdf object that have been found after the searched string.
	
	    RETURN VALUE
	        True if the searched string has been found and is followed by an object or array of object references,
		false otherwise.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetObjectReferences ( $object_id, $object_data, $searched_string, &$object_ids )
	   {
		$status		=  true ;
		$object_ids	=  array ( ) ;

		if  ( preg_match ( "#$searched_string \s* \\[ (?P<objects> [^\]]+ ) \\]#ix", $object_data, $match ) )
		   {
			$object_list	=  $match [ 'objects' ] ;

			if  ( preg_match_all ( '/(?P<object> \d+) \s+ \d+ \s+ R/x', $object_list, $matches ) )
			   {
				foreach  ( $matches [ 'object' ]  as  $id )
					$object_ids []	=  ( integer ) $id ;
			    }
			else
				$status		=  false ;
		    }
		else if  ( preg_match ( "#$searched_string \s+ (?P<object> \d+) \s+ \d+ \s+ R#ix", $object_data, $match ) )
		   {
			$object_ids []	=  ( integer ) $match [ 'object' ] ;
		    }
		else
			$status		=  false ;

		return ( $status ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetStringParameter - Retrieve a string flag value.
	
	    PROTOTYPE
	        $result		=  $this -> GetStringParameter ( $parameter, $object_data ) ;
	
	    DESCRIPTION
	        Retrieves the value of a string parameter ; for example :

			/U (parameter value)

		or :

			/U <hexdigits>
	
	    PARAMETERS
	        $parameter (string) -
	                Parameter name.

		$object_data (string) -
			Object containing the parameter.
	
	    RETURN VALUE
	        The parameter value.
	
	    NOTES
	        description
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetStringParameter ( $parameter, $object_data )
	   { 
		if  ( preg_match ( '#' . $parameter . ' \s* \( \s* (?P<value> [^)]+) \)#ix', $object_data, $match ) )
			$result		=  $this -> ProcessEscapedString ( $match [ 'value' ] ) ;
		else if  ( preg_match ( '#' . $parameter . ' \s* \< \s* (?P<value> [^>]+) \>#ix', $object_data, $match ) )
		   {
			$hexdigits	=  $match [ 'value' ] ;
			$result		=  '' ;

			for  ( $i = 0, $count = strlen ( $hexdigits ) ; $i  <  $count ; $i +=  2 )
				$result		.=  chr ( hexdec ( substr ( $hexdigits, $i, 2 ) ) ) ;
		    }
		else	
			$result		=  '' ;

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    GetUTCDate -
	        Reformats an Adobe UTC date to a format that can be understood by the strtotime() function.
		Dates are specified in the following format :
			D:20150521154000Z
			D:20160707182114+02
		with are both recognized by strtotime(). However, another format can be specified :
			D:20160707182114+02'00'
		which is not recognized by strtotime() so we have to get rid from the '00' part.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetUTCDate ( $date )
	   {
		if  ( $date )
		   {
			if  ( ( $date [0]  ==  'D'  ||  $date [0]  ==  'd' )  &&  $date [1]  ==  ':' )
				$date	=  substr ( $date, 2 ) ;

			if  ( ( $index  =  strpos ( $date, "'" ) )  !==  false )
				$date	=  substr ( $date, 0, $index ) ;
		    }

		return ( $date ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    IsCharacterMap -
	        Checks if the specified text contents represent a character map definition or not.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsCharacterMap  ( $decoded_data )
	   {
		// preg_match is faster than calling strpos several times
		return ( preg_match ( '#(begincmap)|(beginbfrange)|(beginbfchar)|(/Differences)#ix', $decoded_data ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    IsFont -
		Checks if the current object contents specify a font declaration.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsFont ( $object_data )
	   {
		return 
		   ( 
			stripos ( $object_data, '/BaseFont' )  !==  false  ||
				( ! preg_match ( '#/Type \s* /FontDescriptor#ix', $object_data )  &&
					preg_match ( '#/Type \s* /Font#ix', $object_data ) )
		    ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    IsFormData -
		Checks if the current object contents specify references to font data.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsFormData ( $object_data )
	   {
		return
		   (
			preg_match ( '#\bR \s* \( \s* datasets \s* \)#imsx', $object_data ) 
		    ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    IsFontMap -
		Checks if the code contains things like :
			<</F1 26 0 R/F2 22 0 R/F3 18 0 R>>
		which maps font 1 (when specified with the /Fx instruction) to object 26, 2 to object 22 and 3 to 
		object 18, respectively, in the above example.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsFontMap ( $object_data )
	   {
		$object_data	=  self::UnescapeHexCharacters ( $object_data ) ;

		if  ( preg_match ( '#<< \s* ( ' . self::$FontSpecifiers . ' ) \s+ .* >>#imsx', $object_data ) )
			return ( true ) ;
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    IsImage -
		Checks if the code contains things like :
			/Subtype/Image

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsImage ( $object_data )
	   {
		if  ( preg_match ( '#/Subtype \s* /Image#msx', $object_data ) )
			return ( true ) ;
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    IsObjectStream -
		Checks if the code contains an object stream (/Type/ObjStm)
			/Subtype/Image

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsObjectStream ( $object_data ) 
	   {
		if  ( preg_match ( '#/Type \s* /ObjStm#isx', $object_data ) )
			return ( true ) ;
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        IsPageHeaderOrFooter - Check if the specified object contents denote a text stream.

	    PROTOTYPE
	        $status		=  $this -> IsPageHeaderOrFooter ( $stream_data ) ;

	    DESCRIPTION
	        Checks if the specified decoded stream contents denotes header or footer data.

	    PARAMETERS
	        $stream_data (string) -
	                Decoded stream contents.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsPageHeaderOrFooter ( $stream_data )
	   {
		if  ( preg_match ( '#/Type \s* /Pagination \s* /Subtype \s*/((Header)|(Footer))#ix', $stream_data ) )
			return ( true ) ;
		else if  ( preg_match ( '#/Attached \s* \[ .*? /((Top)|(Bottom)) [^]]#ix', $stream_data ) )
			return ( true ) ;
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        IsText - Check if the specified object contents denote a text stream.

	    PROTOTYPE
	        $status		=  $this -> IsText ( $object_data, $decoded_stream_data ) ;

	    DESCRIPTION
	        Checks if the specified object contents denote a text stream.

	    PARAMETERS
	        $object_data (string) -
	                Object data, ie the contents located between the "obj" and "endobj" keywords.

	        $decoded_stream_data (string) -
	        	The flags specified in the object data are not sufficient to be sure that we have a block of
	        	drawing instructions. We must also check for certain common instructions to be present.

	    RETURN VALUE
	        True if the specified contents MAY be text contents, false otherwise.

	    NOTES
		I do not consider this method as bullet-proof. There may arise some cases where non-text blocks can be
		mistakenly considered as text blocks, so it is subject to evolve in the future.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsText ( $object_data, $decoded_stream_data )
	   {
		if  ( preg_match ( '# / (Filter) | (Length) #ix', $object_data )  &&
		      ! preg_match ( '# / (Type) | (Subtype) | (Length1) #ix', $object_data ) )
		   {
		   	if  ( preg_match ( '/\\b(BT|Tf|Td|TJ|Tj|Tm|Do|cm)\\b/', $decoded_stream_data ) )
				return ( true ) ;
		    }
		else if  ( preg_match ( '/\\b(BT|Tf|Td|TJ|Tj|Tm|Do|cm)\\b/', $decoded_stream_data ) )
			return ( true ) ;

		return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        PregStrReplace - Replace string(s) using regular expression(s)
	
	    PROTOTYPE
	        $result		=  PdfToText::PregStrReplace ( $pattern, $replacement, $subject, $limit = -1, 
						&$match_count = null )
	
	    DESCRIPTION
	        This function behaves like a mix of str_replace() and preg_replace() ; it allows to search for strings
		using regular expressions, but the replacements are plain-text strings and no reference to a capture
		specified in the regular expression will be interpreted.
		This is useful when processing templates, which can contain constructs such as "\00" or "$", which are
		interpreted by preg_replace() as references to captures.

		The function has the same parameters as preg_replace().
	
	    RETURN VALUE
	        Returns the substituted text.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  PregStrReplace ( $pattern, $replacement, $subject, $limit = -1, &$match_count = null )
	   {
		// Make sure that $pattern and $replacement become arrays of the same size
		if  ( is_array ( $pattern ) )
		   {
			if  ( is_array ( $replacement ) )
			   {
				if  ( count ( $pattern )  !==  count ( $replacement ) )
				   {
					warning ( "The \$replacement parameter should have the same number of element as \$pattern." ) ;
					return ( $subject ) ;
				    }
			    }
			else
				$replacement	=  array_fill ( $replacement, count ( $pattern ), $replacement ) ;
		    }
		else 
		   {
			if  ( is_array ( $replacement ) )
			   {
				warning ( "Expected string for the \$replacement parameter." ) ;
				return ( $subject ) ;
			    }

			$pattern	=  array ( $pattern ) ;
			$replacement	=  array ( $replacement ) ;
		    }

		// Upper limit
		if  ( $limit  <  1 )
			$limit		=  PHP_INT_MAX ;

		// Loop through each supplied pattern
		$current_subject	=  $subject ;
		$count			=  0 ;
		
		for  ( $i = 0, $pattern_count = count ( $pattern ) ; $i  <  $pattern_count ; $i ++ )
		   {
			$regex		=  $pattern [$i] ;

			// Get all matches for this pattern
			if  ( preg_match_all ( $regex, $current_subject, $matches, PREG_OFFSET_CAPTURE ) )
			   {
				$result		=  '' ;		// Current output result
				$last_offset	=  0 ;

				// Process each match
				foreach  ( $matches [0]  as  $match )
				   {
					$offset		=  ( integer ) $match [1] ;

					// Append data from the last seen offset up to the current one
					if  ( $last_offset  <  $offset )
						$result		.=  substr ( $current_subject, $last_offset, $offset - $last_offset ) ;

					// Append the replacement string for this match
					$result		.=  $replacement [$i] ;

					// Compute next offset in $current_subject
					$last_offset     =  $offset + strlen ( $match [0] ) ;

					// Limit checking
					$count ++ ;

					if  ( $count  >  $limit )
						break 2 ;
				    }

				// Append the last part of the subject that has not been matched by anything
				$result			.=  substr ( $current_subject, $last_offset ) ;

				// The current subject becomes the string that has been built in the steps above
				$current_subject	 =  $result ;
			    }
		    }

		/// All done, return
		return ( $current_subject ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        ProcessEscapedCharacter - Interprets a character after a backslash in a string.
	
	    PROTOTYPE
	        $ch		=  $this -> ProcessEscapedCharacter ( $ch ) ;
	
	    DESCRIPTION
	        Interprets a character after a backslash in a string and returns the interpreted value.
	
	    PARAMETERS
	        $ch (char) -
	                Character to be escaped.
	
	    RETURN VALUE
	        The escaped character.

	    NOTES
		This method does not process octal sequences.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  ProcessEscapedCharacter ( $ch )
	   {
		switch ( $ch ) 
		   {
			// Normally, only a few characters should be escaped...
			case	'('	:  $newchar =  "("		; break ;
			case	')'	:  $newchar =  ")"		; break ;
			case	'['	:  $newchar =  "["		; break ;
			case	']'	:  $newchar =  "]"		; break ;
			case	'\\'	:  $newchar =  "\\"		; break ;
			case 	'n' 	:  $newchar =  "\n"		; break ;
			case 	'r' 	:  $newchar =  "\r"		; break ;
			case 	'f' 	:  $newchar =  "\f"		; break ;
			case 	't' 	:  $newchar =  "\t"		; break ;
			case 	'b' 	:  $newchar =  chr (  8 )	; break ;
			case 	'v' 	:  $newchar =  chr ( 11 )	; break ;

			// ... but should we consider that it is a heresy to escape other characters ?
			// For the moment, no.
			default		:  $newchar =  $ch  ; break ;
		    }

		return ( $newchar ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        ProcessEscapedString - Processes a string which can have escaped characters.
	
	    PROTOTYPE
	        $result		=  $this -> ProcessEscapedString ( $str, $process_octal_escapes = false ) ;
	
	    DESCRIPTION
	        Processes a string which may contain escape sequences.
	
	    PARAMETERS
	        $str (string) -
	                String to be processed.

		$process_octal_escapes (boolean) -
			When true, octal escape sequences such as \037 are processed.
	
	    RETURN VALUE
	        The processed input string.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  ProcessEscapedString ( $str, $process_octal_escapes = false )
	   {
		$length		=  strlen ( $str ) ;
		$offset		=  0 ;
		$result		=  '' ;
		$ord0		=  ord ( '0' ) ;

		while  ( ( $backslash_index = strpos ( $str, '\\', $offset ) )  !==  false )
		   {
			if  ( $backslash_index + 1  <  $length )
			   {
				$ch		 =  $str [ ++ $backslash_index ] ;

				if  ( ! $process_octal_escapes )
				   {
					$result		.=  substr ( $str, $offset, $backslash_index - $offset - 1 ) . $this -> ProcessEscapedCharacter ( $ch ) ;
					$offset		 =  $backslash_index + 1 ;
				    }
				else if  ( $ch  <  '0'  ||  $ch  >  '7' )
				   {
					$result		.=  substr ( $str, $offset, $backslash_index - $offset - 1 ) . $this -> ProcessEscapedCharacter ( $ch ) ;
					$offset		 =  $backslash_index + 1 ;
				    }
				else
				   {
					$result		.=  substr ( $str, $offset, $backslash_index - $offset - 1 ) ;
					$ord		 =  ord ( $ch ) - $ord0 ;
					$count		 =  0 ;
					$backslash_index ++ ;

					while  ( $backslash_index  <  $length  &&  $count  <  2  &&
							$str [ $backslash_index ]  >=  '0'  &&  $str [ $backslash_index ]  <=  '7' )
					   {
						$ord	=  ( $ord * 8 ) + ( ord ( $str [ $backslash_index ++ ] ) - $ord0 ) ;
						$count ++ ;
					    }

					$result		.=  chr ( $ord ) ;
					$offset		 =  $backslash_index ;
				    }
			    }
			else
				break ;
		    }

		$result		.=  substr ( $str, $offset ) ;

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        Unescape - Processes escape sequences from the specified string.

	    PROTOTYPE
	        $value	=  $this -> Unescape ( $text ) ;

	    DESCRIPTION
	        Processes escape sequences within the specified text. The recognized escape sequences are like the
		C-language ones : \b (backspace), \f (form feed), \r (carriage return), \n (newline), \t (tab).
		All other characters prefixed by "\" are returned as is.

	    PARAMETERS
	        $text (string) -
	                Text to be unescaped.

	    RETURN VALUE
	        Returns the unescaped value of $text.

	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  Unescape ( $text )
	   {
		$length 	=  strlen ( $text ) ;
		$result 	=  '' ;
		$ord0		=  ord ( 0 ) ;

		for  ( $i = 0 ; $i  <  $length ; $i ++ )
		   {
		   	$ch 	=  $text [$i] ;

			if  ( $ch  ==  '\\'  &&  isset ( $text [$i+1] ) )
			   {
				$nch 	=  $text [++$i] ;

				switch  ( $nch )
				   {
				   	case 	'b' 	:  $result .=  "\b" ; break ;
				   	case 	't' 	:  $result .=  "\t" ; break ;
				   	case 	'f' 	:  $result .=  "\f" ; break ;
				   	case 	'r' 	:  $result .=  "\r" ; break ;
				   	case 	'n' 	:  $result .=  "\n" ; break ;
				   	default 	:  
						// Octal escape notation 
						if  ( $nch  >=  '0'  &&  $nch  <=  '7' )
						   {
							$ord		=  ord ( $nch ) - $ord0 ;
							$digits		=  1 ;
							$i ++ ;

							while  ( $i  <  $length  &&  $digits  <  3  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7' )
							   {
								$ord	=  ( $ord * 8 ) + ord ( $text [$i] ) - $ord0 ;
								$i ++ ;
								$digits ++ ;
							    }

							$i -- ;		// Count one character less since $i will be incremented at the end of the for() loop

							$result .= chr ( $ord ) ;
						    }
						else
							$result .=  $nch ;
				    }
			    }
			else
				$result 	.=  $ch ;
		    }

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        UnescapeHexCharacters - Unescapes characters in the #xy notation.
	
	    PROTOTYPE
	        $result		=  $this -> UnescapeHexCharacters ( $data ) ;
	
	    DESCRIPTION
		Some specifications contain hex characters specified as #xy. For the moment, I have met such a construct in
		font aliases such as :
			/C2#5F0 25 0 R
		where "#5F" stands for "_", giving :
			/C2_0 25 0 R
		Hope that such constructs do not happen in other places...
	
	    PARAMETERS
	        $data (string) -
	                String to be unescaped.
	
	    RETURN VALUE
	        The input string with all the hex character representations replaced with their ascii equivalent.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  UnescapeHexCharacters ( $data )
	   {
		if  ( strpos ( $data, 'stream' )  ===  false  &&  preg_match ( '/(?P<hex> \# [0-9a-f] [0-9a-f])/ix', $data ) )
		    {
			preg_match_all ( '/(?P<hex> \# [0-9a-f] [0-9a-f])/ix', $data, $matches ) ;
		  
			$searches		=   array ( ) ;
			$replacements		=   array ( ) ;

			foreach  ( $matches [ 'hex' ]  as  $hex )
			   {
				if  ( ! isset ( $searches [ $hex ] ) )
				   {
					$searches [ $hex ]	=  $hex ;
					$replacements []	=  chr ( hexdec ( substr ( $hex, 1 ) ) ) ;
				    }

				$data	=  str_replace ( $searches, $replacements, $data ) ;
			    }
		     }

		return ( $data ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    ValidatePhpName -
		Checks that the specified name (declared in the XML template) is a valid PHP name.

	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  ValidatePhpName ( $name )
	   {
		$name	=  trim ( $name ) ;

		if  ( ! preg_match ( '/^ [a-z_][a-z0-9_]* $/ix', $name ) )
			error ( new PdfToTextFormException ( "Invalid PHP name \"$name\"." ) ) ;

		return ( $name ) ;
	    }
    }


/*==============================================================================================================

    PdfToText class -
	A class for extracting text from Pdf files.

 ==============================================================================================================*/
class  PdfToText 	extends PdfObjectBase
   {
	// Current version of the class
	const		VERSION					=  "1.6.7" ;

	// Pdf processing options
	const		PDFOPT_NONE				=  0x00000000 ;		// No extra option
	const		PDFOPT_REPEAT_SEPARATOR			=  0x00000001 ;		// Repeats the Separator property if the offset between two text blocks (in array notation)
											// is greater than $this -> MinSpaceWidth
	const		PDFOPT_GET_IMAGE_DATA			=  0x00000002 ;		// Retrieve raw image data in the $ths -> ImageData array
	const		PDFOPT_DECODE_IMAGE_DATA		=  0x00000004 ;		// Creates a jpeg resource for each image
	const		PDFOPT_IGNORE_TEXT_LEADING		=  0x00000008 ;		// Ignore text leading values
	const		PDFOPT_NO_HYPHENATED_WORDS		=  0x00000010 ;		// Join hyphenated words that are split on two lines
	const		PDFOPT_AUTOSAVE_IMAGES			=  0x00000020 ;		// Autosave images ; the ImageFileTemplate property will need to be defined
	const		PDFOPT_ENFORCE_EXECUTION_TIME		=  0x00000040 ;		// Enforces the max_execution_time PHP setting when processing a file. A PdfTexterTimeoutException
											// will be thrown if processing of a single file reaches (time_limit - 1 second) by default
											// The MaxExecutionTime property can be set to modify this default value.
	const		PDFOPT_ENFORCE_GLOBAL_EXECUTION_TIME	=  0x00000080 ;		// Same as PDFOPT_ENFORCE_EXECUTION_TIME, but for all calls to the Load() method of the PdfToText class
											// The MaxGlobalExecutionTime static property can be set to modify the default time limit
	const		PDFOPT_IGNORE_HEADERS_AND_FOOTERS	=  0x00000300 ;		// Ignore headers and footers

	const		PDFOPT_RAW_LAYOUT			=  0x00000000 ;		// Layout rendering : raw (default)
	const		PDFOPT_BASIC_LAYOUT			=  0x00000400 ;		// Layout rendering : basic 

	const		PDFOPT_LAYOUT_MASK			=  0x00000C00 ;		// Mask to isolate the targeted layout

	const		PDFOPT_ENHANCED_STATISTICS		=  0x00001000 ;		// Compute statistics on PDF language instructions
	const		PDFOPT_DEBUG_SHOW_COORDINATES		=  0x00002000 ;		// Include text coordinates ; implies the PDFOPT_BASIC_LAYOUT option
											// This option can be useful if you want to use capture areas and get information about
											// their coordinates
	const		PDFOPT_CAPTURE				=  0x00004000 ;		// Indicates that the caller wants to capture some text and use the SetCaptures() method
											// It currently enables the PDFOPT_BASIC_LAYOUT option
	const		PDFOPT_LOOSE_X_CAPTURE			=  0x00008000 ;		// Includes in captures text fragments whose dimensions may exceed the captured area dimensions
	const		PDFOPT_LOOSE_Y_CAPTURE			=  0x00010000 ;		// (currently not used)

	// When boolean true, outputs debug information about fonts, character maps and drawing contents.
	// When integer > 1, outputs additional information about other objects.
	public static 		$DEBUG 			=  false ;

	// Current filename
	public 			$Filename 			=  false ;
	// Extracted text
	public			$Text				=  '' ;
	// Document pages (array of strings)
	public			$Pages				=  array ( ) ;
	// Document images (array of PdfImage objects)
	public			$Images				=  array ( ) ;
	protected		$ImageCount			=  0 ;
	// Raw data for document images
	public			$ImageData			=  array ( ) ;
	// ImageAutoSaveFileTemplate :
	//	Template for the file names to be generated when extracting images, if the PDFOPT_AUTOSAVE_IMAGES has been specified.
	//	Can contain any path, plus the following printf()-like modifiers :
	//	. "%p" : Path of the original PDF file.
	//	. "%f" : Filename part of the original PDF file.
	//	. "%d" : A sequential number, starting from 1, used when generating filenames. The format can contains a width specifier,
	//		 such as "%3d", which will generate 3-digits sequential numbers left-filled with zeroes.
	//	. "%s" : Image suffix, which will automatically based on the underlying image type.
	public			$ImageAutoSaveFileTemplate	=   "%p/%f.%d.%s" ;
	// Auto-save image file format
	public			$ImageAutoSaveFormat		=  IMG_JPEG ;
	// Auto-saved image file names
	public			$AutoSavedImageFiles		=  array ( ) ;
	// Text chunk separator (used to separate blocks of text specified as an array notation)
	public			$BlockSeparator			=  '' ;
	// Separator used to separate text groups where the offset value is less than -1000 thousands of character units
	// (eg : [(1)-1822(2)] will add a separator between the characters "1" and "2")
	// Note that such values are expressed in thousands of text units and subtracted from the current position. A 
	// negative value means adding more space between the two text units it separates.
	public			$Separator			=  ' ' ;
	// Separator to be used between pages in the $Text property
	public			$PageSeparator			=  "\n" ;
	// Minimum value (in 1/1000 of text units) that separates two text chunks that can be considered as a real space
	public			$MinSpaceWidth			=  200 ;
	// Pdf options
	public			$Options			=  self::PDFOPT_NONE ;
	// Maximum number of pages to extract from the PDF. A zero value means "extract everything"
	// If this number is negative, then the pages to be extract start from the last page. For example, a value of -2
	// extracts the last two pages
	public			$MaxSelectedPages		=  false ;
	// Maximum number of images to be extracted. A value of zero means "extract everything". A non-zero value gives
	// the number of images to extract.
	public			$MaxExtractedImages		=  false ;
	// Location of the CID tables directory
	public static		$CIDTablesDirectory ;
	// Loacation of the Font metrics directory, for the Adobe standard 14 fonts
	public static		$FontMetricsDirectory ;
	// Standard Adobe font names, and their corresponding file in $FontMetricsDirectory
	public static		$AdobeStandardFontMetrics	=  array 
	   (
		'courier'		=>  'courier.fm',
		'courier-bold'		=>  'courierb.fm',
		'courier-oblique'	=>  'courieri.fm',
		'courier-boldoblique'	=>  'courierbi.fm',
		'helvetica'		=>  'helvetica.fm',
		'helvetica-bold'	=>  'helveticab.fm',
		'helvetica-oblique'	=>  'helveticai.fm',
		'helvetica-boldoblique'	=>  'helveticabi.fm',
		'symbol'		=>  'symbol.fm',
		'times-roman'		=>  'times.fm',
		'times-bold'		=>  'timesb.fm',
		'times-bolditalic'	=>  'timesbi.fm',
		'times-italic'		=>  'timesi.fm',
		'zapfdingbats'		=>  'zapfdingbats.fm'
	    ) ;
	// Author information 
	public			$Author				=  '' ;
	public			$CreatorApplication		=  '' ;
	public			$ProducerApplication		=  '' ;
	public			$CreationDate			=  '' ;
	public			$ModificationDate		=  '' ;
	public			$Title				=  '' ;
	public			$Subject			=  '' ;
	public			$Keywords			=  '' ; 
	protected		$GotAuthorInformation		=  false ;
	// Unique and arbitrary file identifier, as specified in the PDF file
	// Well, in fact, there are two IDs, but the PDF specification does not mention the goal of the second one
	public			$ID				=  '' ;
	public			$ID2				=  '' ; 
	// End of line string
	public			$EOL				=  PHP_EOL ;
	// String to be used when no Unicode translation is possible
	public static		$Utf8Placeholder		=  '' ;
	// Information about memory consumption implied by the file currently being loaded 
	public			$MemoryUsage,
				$MemoryPeakUsage ;
	// Offset of the document start (%PDF-x.y)
	public			$DocumentStartOffset ;
	// Debug statistics
	public		$Statistics			=  array ( ) ;
	// Max execution time settings. A positive value means "don't exceed that number of seconds".
	// A negative value means "Don't exceed PHP setting max_execution_time - that number of seconds". If the result
	// is negative, then the default will be "max_execution_time - 1".
	// For those limits to be enforced, you need to specify either the PDFOPT_ENFORCE_EXECUTION_TIME or
	// PDFOPT_ENFORCE_GLOBAL_EXECUTION_TIME options, or both
	public			$MaxExecutionTime		=  -1 ;
	public static		$MaxGlobalExecutionTime		=  -1 ;
	// This property is expressed in percents ; it gives the extra percentage to add to the values computed by
	// the PdfTexterFont::GetStringWidth() method.
	// This is basically used when computing text positions and string lengths with the PDFOPT_BASIC_LAYOUT option :
	// the computed string length is shorter than its actual length (because of extra spacing determined by character
	// kerning in the font data). To determine whether two consecutive blocks of text should be separated by a space,
	// we empirically add this extra percentage to the computed string length. The default is -5%.
	public			$ExtraTextWidth			=  -5 ;

	// Marker stuff. The unprocessed marker list is a sequential array of markers, which will later be dispatched into
	// indexed arrays during their first reference
	protected		$UnprocessedMarkerList		=  array ( 'font' => array ( ) ) ;
	protected		$TextWithFontMarkers		=  array ( ) ;
	
	// Internal variables used when the PDFOPT_ENFORCE_* options are specified
	protected static	$PhpMaxExecutionTime ;
	protected static	$GlobalExecutionStartTime ;
	protected static	$AllowedGlobalExecutionTime ;
	protected		$ExecutionStartTime ;
	protected		$AllowedExecutionTime ;

	// Font mappings
	protected 		$FontTable			=  false ;
	// Extra Adobe standard font mappings (for character names of the form "/axxx" for example)
	protected		$AdobeExtraMappings		=  array ( ) ;
	// Page map object
	protected		$PageMap ;
	// Page locations (start and end offsets)
	protected		$PageLocations ;
	// Encryption data
	public			$IsEncrypted			=  false ;
	protected		$EncryptionData			=  false ;
	// A flag coming from the constructor options, telling if enhanced statistics are enabled
	protected		$EnhancedStatistics ;

	// Document text fragments, with their absolute (x,y) position, approximate width and height
	protected		$DocumentFragments ;

	// Form data 
	protected		$FormData ;
	protected		$FormDataObjectNumbers ;
	protected		$FormDataDefinitions ;
	protected		$FormaDataObjects ;

	// Capture data
	public			$CaptureDefinitions ;
	protected		$CaptureObject ;

	// Indicates whether global static initializations have been made
	// This is mainly used for variables such as $Utf8PlaceHolder, which is initialized to a different value
	private static		$StaticInitialized		=  false ;

	// Drawing instructions that are to be ignored and removed from a text stream before processing, for performance 
	// reasons (it is faster to call preg_replace() once to remove them than calling the __next_instruction() and 
	// __next_token() methods to process an input stream containing such useless instructions)
	// This is an array of regular expressions where the following constructs are replaced at runtime during static
	// initialization :
	// %n - Will be replaced with a regex matching a decimal number.
	private static		$IgnoredInstructionTemplatesLayout	=  array
	   (
		'%n{6} ( (c) ) \s+',
		'%n{4} ( (re) | (y) | (v) | (k) | (K) ) \s+',
		'%n{3} ( (scn) | (SCN) | (r) | (rg) | (RG) | (sc) | (SC) ) \s+',
		'%n{2} ( (m) | (l) ) \s+',
		'%n ( (w) | (M) | (g) | (G) | (J) | (j) | (d) | (i) | (sc) | (SC) | (Tc) | (Tw) | (scn) | (Tr) | (Tz) | (Ts) ) \s+',
		'\b ( (BDC) | (EMC) ) \s+',
		'\/( (Cs \d+) | (CS \d+) | (G[Ss] \d+) | (Fm \d+) | (Im \d+) | (PlacedGraphic) ) \s+ \w+ \s*',
		'\/( (Span) | (Artifact) | (Figure) | (P) ) \s* << .*? >> [ \t\r\n>]*',
		'\/ ( (PlacedGraphic) | (Artifact) ) \s+',
		'\d+ \s+ ( (scn) | (SCN) )',
		'\/MC \d+ \s+',
		 '^ \s* [fhS] \r? \n',
		 '^W \s+ n \r? \n',
		 '(f | W) \* \s+',
		 '^[fhnS] \s+',
		 '-?0 (\. \d+)? \s+ T[cw]',
		 '\bBI \s+ .*? \bID \s+ .*? \bEI',
		 '\/ \w+ \s+ ( (cs) | (CS) | (ri) | (gs) )',
		 // Hazardous replaces ?
		 '( [Ww] \s+ ){3,}',			
		 ' \[\] \s+ [Shs] \s+'
	    ) ;
	// Additional instructions to be stripped when no particular page layout has been requested
	private static		$IgnoredInstructionTemplatesNoLayout	=  array 
	   (
		'%n{6} ( (cm) ) \s+',
//		'\b ( (BT) | (ET) ) \s+',
		 '^ \s* [Qq] \r? \n',
		 '^ \s* (\b [a-zA-Z] \s+)+',
		 '\s* (\b [a-zA-Z] \s+)+$',
		 '^[qQ] \s+',
		 '^q \s+ [hfS] \n',
		 '( [Qfhnq] \s+ ){2,}'
	    ) ;
	// Replacement regular expressions for %something constructs specified in the $IgnoredInstructions array
	private static		$ReplacementConstructs		=  array
	    (
		'%n'	=>  '( [+\-]? ( ( [0-9]+ ( \. [0-9]* )? ) | ( \. [0-9]+ ) ) \s+ )'
	     ) ;
	// The final regexes that are built during static initialization by the __build_ignored_instructions() method
	private static		$IgnoredInstructionsNoLayout	=  array ( ) ;
	private static		$IgnoredInstructionsLayout	=  array ( ) ;
	private			$IgnoredInstructions		=  array ( ) ;

	// Map id buffer - for avoiding unneccesary calls to GetFontByMapId
	private			$MapIdBuffer			=  array ( ) ;

	// Same for MapCharacter()
	private			$CharacterMapBuffer		=  array ( ) ;

	// Font objects buffer - used by __assemble_text_fragments()
	private			$FontObjectsBuffer		=  array ( ) ;

	// Regex used for removing hyphens - we have to take care of different line endings : "\n" for Unix, "\r\n"
	// for Windows, and "\r" for pure Mac files.
	// Note that we replace an hyphen followed by an end-of-line then by non-space characters with the non-space
	// characters, so the word gets joined on the same line. Spaces after the end of the word (on the next line)
	// are removed, in order for the next word to appear at the beginning of the second line.
	private static		$RemoveHyphensRegex		=  '#
									( 
										  -
										  [ \t]* ( (\r\n) | \n | \r )+ [ \t\r\n]*
									 )
									([^ \t\r\n]+)
									\s*
								    #msx' ;

	// A small list of Unicode character ranges that are related to languages written from right to left
	// For performance reasons, everythings is mapped to a range here, even if it includes codepoints that do not map to anything
	// (this class is not a Unicode codepoint validator, but a Pdf text extractor...)
	// The UTF-16 version is given as comments ; only the UTF-8 translation is used here
	// To be completed !
	private static		$RtlCharacters			=  array
	   (
		// This range represents the following languages :
		// - Hebrew			(0590..05FF)
		// - Arabic			(0600..06FF)
		// - Syriac			(0700..074F)
		// - Supplement for Arabic	(0750..077F)
		// - Thaana			(0780..07BF)
		// - N'ko			(07C0..07FF)
		// - Samaritan			(0800..083F)
		// - Mandaic			(0840..085F)
		//	array ( 0x00590, 0x0085F ),
		// Hebrew supplement (I suppose ?) + other characters
		//	array ( 0x0FB1D, 0x0FEFC ),
		// Mende kikakui
		//	array ( 0x1E800, 0x1E8DF ),
		// Adlam
		//	array ( 0x1E900, 0x1E95F ),
		// Others
		//	 array ( 0x10800, 0x10C48 ),
		//	 array ( 0x1EE00, 0x1EEBB )
		"\xD6"		=>  array ( array ( "\x90", "\xBF" ) ),
		"\xD7"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xD8"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xD9"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDA"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDB"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDC"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDD"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDE"		=>  array ( array ( "\x80", "\xBF" ) ),
		"\xDF"		=>  array ( array ( "\x80", "\xBF" ) )
		/*
		"\xE0"		=>  array 
		   (
			array ( "\xA0\x80", "\xA0\xBF" ),
			array ( "\xA1\x80", "\xA1\x9F" )
		    ),
		"\xEF"		=>  array
		   (
			array ( "\xAC\x9D", "\xAC\xBF" ),
			array ( "\xAD\x80", "\xAD\xBF" ),
			array ( "\xAE\x80", "\xAE\xBF" ),
			array ( "\xAF\x80", "\xAF\xBF" ),
			array ( "\xB0\x80", "\xB0\xBF" ),
			array ( "\xB1\x80", "\xB1\xBF" ),
			array ( "\xB2\x80", "\xB2\xBF" ),
			array ( "\xB3\x80", "\xB3\xBF" ),
			array ( "\xB4\x80", "\xB4\xBF" ),
			array ( "\xB5\x80", "\xB5\xBF" ),
			array ( "\xB6\x80", "\xB6\xBF" ),
			array ( "\xB7\x80", "\xB7\xBF" ),
			array ( "\xB8\x80", "\xB8\xBF" ),
			array ( "\xB9\x80", "\xB9\xBF" ),
			array ( "\xBA\x80", "\xBA\xBF" ),
			array ( "\xBB\x80", "\xBB\xBC" )
		    )
		    */
	    ) ;

	// UTF-8 prefixes for RTL characters as keys, and number of characters that must follow the prefix as values
	private static		$RtlCharacterPrefixLengths	=  array
	   (
		"\xD6"		=>  1,
		"\xD7"		=>  1,
		"\xD8"		=>  1,
		"\xD9"		=>  1,
		"\xDA"		=>  1,
		"\xDB"		=>  1,
		"\xDC"		=>  1,
		"\xDE"		=>  1,
		"\xDF"		=>  1
		/*
		"\xE0"		=>  2,
		"\xEF"		=>  2
		*/
	    ) ;

	// A string that contains all the RTL character prefixes above
	private static		$RtlCharacterPrefixes ;

	// As usual, caching a little bit the results of the IsRtlCharacter() method is welcome. Each item will have the value true if the
	// character is RTL, or false if LTR.
	private			$RtlCharacterBuffer		=  array ( ) ;

	// A subset of a character classification array that avoids too many calls to the ctype_* functions or too many
	// character comparisons.
	// This array is used only for highly sollicited parts of code
	const	CTYPE_ALPHA		=  0x01 ;		// Letter
	const	CTYPE_DIGIT		=  0x02 ;		// Digit
	const	CTYPE_XDIGIT		=  0x04 ;		// Hex digit
	const	CTYPE_ALNUM		=  0x08 ;		// Letter or digit
	const	CTYPE_LOWER		=  0x10 ;		// Lower- or upper-case letters
	const	CTYPE_UPPER		=  0x20 ;

	private static		$CharacterClasses		=  false ;

	// Stuff specific to the current PHP version
	private static		$HasMemoryGetUsage ;
	private static		$HasMemoryGetPeakUsage ;


	/*--------------------------------------------------------------------------------------------------------------

	    CONSTRUCTOR
	        $pdf	=  new PdfToText ( $filename = null, $options = PDFOPT_NONE ) ;

	    DESCRIPTION
	        Builds a PdfToText object and optionally loads the specified file's contents.

	    PARAMETERS
	        $filename (string) -
	                Optional PDF filename whose text contents are to be extracted.

		$options (integer) -
			A combination of PDFOPT_* flags. This can be any of the following :

			- PDFOPT_REPEAT_SEPARATOR :
				Text constructs specified as an array are separated by an offset which is expressed as
				thousands of text units ; for example :

					[(1)-2000(2)]

				will be rendered as the text "1  2" ("1" and "2" being separated by two spaces) if the
				"Separator" property is set to a space (the default) and this flag is specified.
				When not specified, the text will be rendered as "1 2".

			- PDFOPT_NONE :
				None of the above options will apply.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $filename = null, $options = self::PDFOPT_NONE, $user_password = false, $owner_password = false )
	   {
		// We need the mbstring PHP extension here...
		if  ( ! function_exists ( 'mb_convert_encoding' ) )
			error ( "You must enable the mbstring PHP extension to use this class." ) ;

		// Perform static initializations if needed
		if  ( ! self::$StaticInitialized )
		   {
			if  ( self::$DEBUG )
			   {
				// In debug mode, initialize the utf8 placeholder only if it still set to its default value, the empty string
				if  ( self::$Utf8Placeholder  ==  '' )
					self::$Utf8Placeholder	=  '[Unknown character : 0x%08X]' ;
			    }

			// Build the list of regular expressions from the list of ignored instruction templates
			self::__build_ignored_instructions (  ) ;

			// Check if some functions are supported or not
			self::$HasMemoryGetUsage	=  function_exists ( 'memory_get_usage' ) ;
			self::$HasMemoryGetPeakUsage	=  function_exists ( 'memory_get_peak_usage' ) ;

			// Location of the directory containing CID fonts
			self::$CIDTablesDirectory	=  dirname ( __FILE__ ) . DIRECTORY_SEPARATOR . 'CIDTables' ;
			self::$FontMetricsDirectory	=  dirname ( __FILE__ ) . DIRECTORY_SEPARATOR . 'FontMetrics' ;

			// The string that contains all the Rtl character prefixes in UTF-8 - An optimization used by the __rtl_process() method
			self::$RtlCharacterPrefixes	=  implode ( '', array_keys ( self::$RtlCharacterPrefixLengths ) ) ;

			// Build the character classes (used only for testing letters and digits)
			if  ( self::$CharacterClasses  ===  false )
			   {
				for  ( $ord = 0 ; $ord  <  256 ; $ord ++ )
				   {
					$ch	=  chr ( $ord ) ;

					if  ( $ch  >=  '0'  &&  $ch  <=  '9' )
						self::$CharacterClasses [ $ch ]		=  self::CTYPE_DIGIT | self::CTYPE_XDIGIT | self::CTYPE_ALNUM ;
					else if  ( $ch  >=  'A'  &&  $ch  <=  'Z' )
					   {
						self::$CharacterClasses [ $ch ]		=  self::CTYPE_ALPHA | self::CTYPE_UPPER | self::CTYPE_ALNUM ;

						if  ( $ch  <=  'F' )
							self::$CharacterClasses [ $ch ]		|=  self::CTYPE_XDIGIT ;
					    }
					else if  ( $ch  >=  'a'  &&  $ch  <=  'z' )
					   {
						self::$CharacterClasses [ $ch ]		=  self::CTYPE_ALPHA | self::CTYPE_LOWER | self::CTYPE_ALNUM ;

						if  ( $ch  <=  'f' )
							self::$CharacterClasses [ $ch ]		|=  self::CTYPE_XDIGIT ;
					    }
					else
						self::$CharacterClasses [ $ch ]		=  0 ;
				    }
			    }

			// Global execution time limit
			self::$PhpMaxExecutionTime	=  ( integer ) ini_get ( 'max_execution_time' ) ;

			if  ( ! self::$PhpMaxExecutionTime )					// Paranoia : default max script execution time to 120 seconds
				self::$PhpMaxExecutionTime	=  120 ;

			self::$GlobalExecutionStartTime		=  microtime ( true ) ;		// Set the start of the first execution

			if  ( self::$MaxGlobalExecutionTime  >  0 )
				self::$AllowedGlobalExecutionTime	=  self::$MaxGlobalExecutionTime ;
			else
				self::$AllowedGlobalExecutionTime	=  self::$PhpMaxExecutionTime + self::$MaxGlobalExecutionTime ;

			// Adjust in case of inconsistent values
			if  ( self::$AllowedGlobalExecutionTime  <  0  ||  self::$AllowedGlobalExecutionTime  >  self::$PhpMaxExecutionTime )
				self::$AllowedGlobalExecutionTime	=  self::$PhpMaxExecutionTime - 1 ;

			self::$StaticInitialized	=  true ;
		    }

		parent::__construct ( ) ;

		$this -> Options		=  $options ;

		if  ( $filename )
			$this -> Load ( $filename, $user_password, $owner_password ) ;
	    }


	public function  __tostring ( )
	   { return ( $this -> Text ) ; }


	/**************************************************************************************************************
	 **************************************************************************************************************
	 **************************************************************************************************************
	 ******                                                                                                  ******
	 ******                                                                                                  ******
	 ******                                          PUBLIC METHODS                                          ******
	 ******                                                                                                  ******
	 ******                                                                                                  ******
	 **************************************************************************************************************
	 **************************************************************************************************************
	 **************************************************************************************************************/

	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        Load		- Loads text contents from a PDF file.
		LoadFromString	- Loads PDF contents from a string.

	    PROTOTYPE
	        $text	=  $pdf -> Load ( $filename, $user_password = false, $owner_password = false ) ;
	        $text	=  $pdf -> LoadFromString ( $contents, $user_password = false, $owner_password = false ) ;

	    DESCRIPTION
	        The Load() method extracts text contents from the specified PDF file. Once processed, text contents will 
		be available through the "Text" property.
		The LoadFromString() method performs the same operation on PDF contents already loaded into memory.

	    PARAMETERS
	        $filename (string) -
	                Optional PDF filename whose text contents are to be extracted.

		$contents (string) -
			String containing PDF contents.

		$user_password (string) -
			User password used for decrypting PDF contents.

		$owner_password (string) -
			Owner password.

	 *-------------------------------------------------------------------------------------------------------------*/
	private		$__memory_peak_usage_start,
			$__memory_usage_start ;

	public function  Load  ( $filename, $user_password = false, $owner_password = false )
	   {
		$this -> __memory_usage_start		=  ( self::$HasMemoryGetUsage     ) ?  memory_get_usage      ( true ) : 0 ;
		$this -> __memory_peak_usage_start	=  ( self::$HasMemoryGetPeakUsage ) ?  memory_get_peak_usage ( true ) : 0 ;

		// Check if the file exists, but only if the file is on a local filesystem
		if  ( ! preg_match ( '#^ [^:]+ ://#ix', $filename )  && ! file_exists ( $filename ) )
			error ( new  PdfToTextDecodingException ( "File \"$filename\" does not exist." ) ) ;

		// Load its contents
		$contents 	=  @file_get_contents ( $filename, FILE_BINARY ) ;

		if  ( $contents  ===  false ) 
			error ( new  PdfToTextDecodingException ( "Unable to open \"$filename\"." ) ) ;

		return ( $this -> __load ( $filename, $contents, $user_password, $owner_password ) ) ;		
	    }


	public function  LoadFromString ( $contents, $user_password = false, $owner_password = false )
	   {
		$this -> __memory_usage_start		=  ( self::$HasMemoryGetUsage     ) ?  memory_get_usage      ( true ) : 0 ;
		$this -> __memory_peak_usage_start	=  ( self::$HasMemoryGetPeakUsage ) ?  memory_get_peak_usage ( true ) : 0 ;

		return ( $this -> __load ( '', $contents, $user_password, $owner_password ) ) ;				
	    }


	private function  __load ( $filename, $contents, $user_password = false, $owner_password = false )
	   {
		// Search for the start of the document ("%PDF-x.y")
		$start_offset	=  strpos ( $contents, '%PDF' ) ;

		if  ( $start_offset  ===  false )		// Not a pdf document !
			error ( new PdfToTextDecodingException ( "File \"$filename\" is not a valid PDF file." ) ) ;
		else						// May be a PDF document
			$this -> DocumentStartOffset	=  $start_offset ;

		// Check that this is a PDF file with a valid version number
		if  ( ! preg_match ( '/ %PDF- (?P<version> \d+ (\. \d+)*) /ix', $contents, $match, 0, $start_offset ) )
			error ( new PdfToTextDecodingException ( "File \"$filename\" is not a valid PDF file." ) ) ;

		$this -> PdfVersion 		=  $match [ 'version' ] ;

		// Initializations
		$this -> Text 				=  '' ;
		$this -> FontTable 			=  new PdfTexterFontTable ( ) ;
		$this -> Filename 			=  realpath ( $filename ) ;
		$this -> Pages				=  array ( ) ;
		$this -> Images				=  array ( ) ;
		$this -> ImageData			=  array ( ) ;
		$this -> ImageCount			=  0 ;
		$this -> AutoSavedImageFiles		=  array ( ) ;
		$this -> PageMap			=  new PdfTexterPageMap ( ) ;
		$this -> PageLocations			=  array ( ) ;
		$this -> Author				=  '' ;
		$this -> CreatorApplication		=  '' ;
		$this -> ProducerApplication		=  '' ;
		$this -> CreationDate			=  '' ;
		$this -> ModificationDate		=  '' ;
		$this -> Title				=  '' ;
		$this -> Subject			=  '' ;
		$this -> Keywords			=  '' ;
		$this -> GotAuthorInformation		=  false ;
		$this -> ID				=  '' ;
		$this -> ID2				=  '' ; 
		$this -> EncryptionData			=  false ;
		$this -> EnhancedStatistics		=  ( ( $this -> Options  &  self::PDFOPT_ENHANCED_STATISTICS )  !=  0 ) ;

		// Also reset cached information that may come from previous runs
		$this -> MapIdBuffer			=  array ( ) ;
		$this -> RtlCharacterBuffer		=  array ( ) ;
		$this -> CharacterMapBuffer		=  array ( ) ;
		$this -> FontObjectsBuffer		=  array ( ) ;
		$this -> FormData			=  array ( ) ;
		$this -> FormDataObjectNumbers		=  false ;
		$this -> FomDataDefinitions		=  array ( ) ;
		$this -> FormDataObjects		=  array ( ) ;
		$this -> CaptureDefinitions		=  false ;
		$this -> CaptureObject			=  false ; 
		$this -> DocumentFragments		=  array ( ) ;

		// Enable the PDFOPT_BASIC_LAYOUT option if the PDFOPT_CAPTURE flag is specified
		if  ( $this -> Options & self::PDFOPT_CAPTURE )
			$this -> Options	|=  self::PDFOPT_BASIC_LAYOUT ;

		// Enable the PDFOPT_BASIC_LAYOUT_OPTION is PDFOPT_DEBUG_SHOW_COORDINATES is specified
		if  ( $this -> Options & self::PDFOPT_DEBUG_SHOW_COORDINATES )
			$this -> Options	|=  self::PDFOPT_BASIC_LAYOUT ;

		// Page layout options needs more instructions to be retained - select the appropriate list of useless instructions
		if  ( $this -> Options  &  self::PDFOPT_BASIC_LAYOUT )
			$this -> IgnoredInstructions	=  self::$IgnoredInstructionsLayout ;
		else
			$this -> IgnoredInstructions	=  self::$IgnoredInstructionsNoLayout ;


		// Debug statistics 
		$this -> Statistics			=  array
		   (
			'TextSize'			=>  0,				// Total size of drawing instructions ("text" objects)
			'OptimizedTextSize'		=>  0,				// Optimized text size, with useless instructions removed
			'Distributions'			=>  array			// Statistics about handled instructions distribution - Works only with the page layout option in debug mode
			   (
				'operand'	=>  0,
				'Tm'		=>  0,
				'Td'		=>  0,
				'TD'		=>  0,
				"'"		=>  0,
				'TJ'		=>  0,
				'Tj'		=>  0,
				'Tf'		=>  0,
				'TL'		=>  0,
				'T*'		=>  0,
				'('		=>  0,
				'<'		=>  0,
				'['		=>  0,
				'cm'		=>  0,
				'BT'		=>  0,
				'template'	=>  0,
				'ignored'	=>  0,
				'space'		=>  0
			    )
		    ) ;

		// Per-instance execution time limit
		$this -> ExecutionStartTime		=  microtime ( true ) ;

		if  ( $this -> MaxExecutionTime  >  0 )
			$this -> AllowedExecutionTime		=  $this -> MaxExecutionTime ;
		else
			$this -> AllowedExecutionTime		=  self::$PhpMaxExecutionTime + $this -> MaxExecutionTime ;

		// Adjust in case of inconsistent values
		if  ( $this -> AllowedExecutionTime  <  0  ||  $this -> AllowedExecutionTime  >  self::$PhpMaxExecutionTime )
			$this -> AllowedExecutionTime		=  self::$PhpMaxExecutionTime - 1 ;

		// Systematically set the DECODE_IMAGE_DATA flag if the AUTOSAVE_IMAGES flag has been specified
		if  ( $this -> Options  &  self::PDFOPT_AUTOSAVE_IMAGES )
			$this -> Options	|=  self::PDFOPT_DECODE_IMAGE_DATA ;

		// Systematically set the GET_IMAGE_DATA flag if DECODE_IMAGE_DATA is specified (debug mode only)
		if  ( self::$DEBUG  &&  $this -> Options  &  self::PDFOPT_DECODE_IMAGE_DATA )
			$this -> Options	|=  self::PDFOPT_GET_IMAGE_DATA ;

		// Since page layout options take 2 bits, but not all of the 4 possible values are allowed, make sure that an invalid
		// value will default to PDFOPT_RAW_LAYOUT value
		$layout_option		=  $this -> Options & self::PDFOPT_LAYOUT_MASK ;

		if  ( ! $layout_option  ===  self::PDFOPT_RAW_LAYOUT  &&  $layout_option  !==  self::PDFOPT_BASIC_LAYOUT )
		   {
			$layout_option		=  self::PDFOPT_RAW_LAYOUT ;
			$this -> Options	=  ( $this -> Options & ~self::PDFOPT_LAYOUT_MASK ) | self::PDFOPT_RAW_LAYOUT ;
		    }

		// Author information needs to be processed after, because it may reference objects that occur later in the PDF stream
		$author_information_object_id		=  false ;

		// Extract pdf objects that are enclosed by the "obj" and "endobj" keywords
		$pdf_objects		=  array ( ) ;
		$contents_offset	=  $this -> DocumentStartOffset ;
		$contents_length	=  strlen ( $contents ) ;


		while  ( $contents_offset  <  $contents_length  &&  
				preg_match ( '/(?P<re> (?P<object_id> \d+) \s+ \d+ \s+ obj (?P<object> .*?) endobj )/imsx', $contents, $match, PREG_OFFSET_CAPTURE, $contents_offset ) )
		   {
			$object_number		=  $match [ 'object_id' ] [0] ;
			$object_data		=  $match [ 'object' ] [0] ;

			// Handle the special case of object streams (compound objects)
			// They are not added in the $pdf_objects array, because they could be mistakenly processed as relevant information,
			// such as font definitions, etc.
			// Instead, only the objects they are embedding are stored in this array.
			if  ( $this -> IsObjectStream ( $object_data ) )
			   {
				// Ignore ill-formed object streams
				if  ( ( $object_stream_matches = $this -> DecodeObjectStream ( $object_number, $object_data ) )  !==  false )
				   {
					// Add this list of objects to the list of known objects
					for  ( $j = 0, $object_stream_count = count ( $object_stream_matches [ 'object_id' ] ) ; $j  <  $object_stream_count ; $j ++ )
						$pdf_objects [ $object_stream_matches [ 'object_id' ] [$j] ]	=  $object_stream_matches [ 'object' ] [$j] ;
				    }
			    }
			// Normal (non-compound) object
			else
				$pdf_objects [ $object_number ]	=  $object_data ;

			// Update current offset through PDF contents
			$contents_offset	=  $match [ 're' ] [1] + strlen ( $match [ 're' ] [0] ) ;
		    }

		// We put a particular attention in treating errors returned by preg_match_all() here, since we need to be really sure why stopped
		// to find further PDF objects in the supplied contents
		$preg_error	=  preg_last_error ( ) ;

		switch  ( $preg_error )
		   {
			case  PREG_NO_ERROR :
				break ;

			case  PREG_INTERNAL_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : the preg_match_all() function encountered an internal error." ) ) ;

			case  PREG_BACKTRACK_LIMIT_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : backtrack limit reached (you may have to modify the pcre.backtrack_limit " .
						"setting of your PHP.ini file, which is currently set to " . ini_get ( 'pcre.backtrack_limit' ) . ")." ) ) ;

			case  PREG_JIT_STACKLIMIT_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : JIT stack limit reached (you may disable this feature by setting the pcre.jit " .
						"setting of your PHP.ini file to 0)." ) ) ;

			case  PREG_RECURSION_LIMIT_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : recursion limit reached (you may have to modify the pcre.recursion_limit " .
						"setting of your PHP.ini file, which is currently set to " . ini_get ( 'pcre.recursion_limit' ) . ")." ) ) ;

			case  PREG_BAD_UTF8_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : bad UTF8 character encountered." ) ) ;

			case PREG_BAD_UTF8_OFFSET_ERROR :
				error ( new PdfToTextDecodingException ( "PDF object extraction : the specified offset does not start at the beginning of a valid UTF8 codepoint." ) ) ;

			default :
				error ( new PdfToTextDecodingException ( "PDF object extraction : unkown PREG error #$preg_error" ) ) ;
		    }


		// Extract trailer information, which may contain the ID of an object specifying encryption flags
		$this -> GetTrailerInformation ( $contents, $pdf_objects ) ;
		unset ( $contents ) ;

		// Character maps encountered so far
		$cmaps			=  array ( ) ;

		// An array that will store object ids as keys and text contents as values
		$text			=  array ( ) ;

		// Loop through the objects
		foreach  ( $pdf_objects  as  $object_number => $object_data )
		   {
			// Some additional objects may be uncovered after processing (in an object containing compacted objects for example)
			// so add them to the list if necessary
			if  ( ! isset ( $pdf_objects [ $object_number ] ) )
				$pdf_objects [ $object_number ]		=  $object_data ;

			// Try to catch information related to page mapping - but don't discard the object since it can contain additional information
			$this -> PageMap -> Peek ( $object_number, $object_data, $pdf_objects ) ;

			// Check if the object contais authoring information - it can appear encoded or unencoded
			if  ( ! $this -> GotAuthorInformation ) 
				$author_information_object_id	=  $this -> PeekAuthorInformation ( $object_number, $object_data ) ;

			// Also catch the object encoding type
			$type 		=  $this -> GetEncodingType ( $object_number, $object_data ) ;
			$stream_match	=  null ;

			if  ( strpos ( $object_data, 'stream' )  ===  false  ||  
					! preg_match ( '#[^/] stream \s+ (?P<stream> .*?) endstream#imsx', $object_data, $stream_match ) )
			   {
				// Some font definitions are in clear text in an object, some are encoded in a stream within the object
				// We process here the unencoded ones
				if  ( $this -> IsFont ( $object_data ) )
				   {
					$this -> FontTable -> Add ( $object_number, $object_data, $pdf_objects, $this -> AdobeExtraMappings ) ;
					continue ;
				    }
				// Some character maps may also be in clear text
				else if  ( $this -> IsCharacterMap ( $object_data ) )
				    {
					$cmap	=  PdfTexterCharacterMap::CreateInstance ( $object_number, $object_data, $this -> AdobeExtraMappings ) ;

					if  ( $cmap )
						$cmaps [] 	=  $cmap ;

					continue ;
				    }
				// Check if there is an association between font number and object number
				else if  ( $this -> IsFontMap ( $object_data ) )
				   {
					$this -> FontTable -> AddFontMap ( $object_number, $object_data ) ;
				    }
				// Retrieve form data if present
				else if  ( $this -> IsFormData ( $object_data ) )
				   {
					$this -> RetrieveFormData ( $object_number, $object_data, $pdf_objects ) ;
				    }
				// Ignore other objects that do not contain an encoded stream
				else 
				   {
					if  ( self::$DEBUG  >  1 )
						echo "\n----------------------------------- UNSTREAMED #$object_number\n$object_data" ;

					continue ;
				    }
			    }
			// Extract image data, if any
			else if  ( $this -> IsImage ( $object_data ) )
			   {
				$this -> AddImage ( $object_number, $stream_match [ 'stream' ], $type, $object_data ) ;
				continue ;
			    }
			// Check if there is an association between font number and object number
			else if  ( $this -> IsFontMap ( $object_data ) )
			   {
				$this -> FontTable -> AddFontMap ( $object_number, $object_data ) ;

				if  ( ! $stream_match )
					continue ;
			    }

			// Check if the stream contains data (yes, I have found a sample that had streams of length 0...)
			// In other words : ignore empty streams
			if  ( stripos ( $object_data, '/Length 0' )  !==  false )
				continue ;

			// Isolate stream data and try to find its encoding type
			if  ( isset  ( $stream_match [ 'stream' ] ) )
				$stream_data 		=  ltrim ( $stream_match [ 'stream' ], "\r\n" ) ;
			else
				continue ;

			// Ignore this stream if the object does not contain an encoding type (/FLATEDECODE, /ASCIIHEX or /ASCII85)
			if  ( $type  ==  self::PDF_UNKNOWN_ENCODING )
			   {
				if  ( self::$DEBUG  >  1 )
					echo "\n----------------------------------- UNENCODED #$object_number :\n$object_data" ;

				continue ;
			    }

			// Decode the encoded stream
			$decoded_stream_data 	=  $this -> DecodeData ( $object_number, $stream_data, $type, $object_data ) ;

			// Second chance to peek author information, this time on a decoded stream data
			if  ( ! $this -> GotAuthorInformation )
				$author_information_object_id	=  $this -> PeekAuthorInformation ( $object_number, $decoded_stream_data ) ;

			// Check for character maps
			if  ( $this -> IsCharacterMap ( $decoded_stream_data ) )
			   {
				$cmap	=  PdfTexterCharacterMap::CreateInstance ( $object_number, $decoded_stream_data, $this -> AdobeExtraMappings ) ;

				if  ( $cmap )
					$cmaps [] 	=  $cmap ;
			   }
			// Font definitions
			else if  ( $this -> IsFont ( $decoded_stream_data ) )
			   {
				$this -> FontTable -> Add ( $object_number, $decoded_stream_data, $pdf_objects, $this -> AdobeExtraMappings ) ;
			    }
			// Retrieve form data if present
			else if  ( $this -> IsFormData ( $object_data ) )
			   {
				$this -> RetrieveFormData ( $object_number, $decoded_stream_data, $pdf_objects ) ;
			    }
			// Plain text (well, in fact PDF drawing instructions)
			else if  ( $this -> IsText ( $object_data, $decoded_stream_data ) )
			   {
				$text_data	=  false ;

				// Check if we need to ignore page headers and footers
				if  ( $this -> Options  &  self::PDFOPT_IGNORE_HEADERS_AND_FOOTERS )
				   {
					if  ( ! $this -> IsPageHeaderOrFooter ( $decoded_stream_data ) )
					   {
						$text [ $object_number ]	=  
						$text_data			=  $decoded_stream_data ;
					    }
					// However, they may be mixed with actual text contents so we need to separate them...
					else
					   {
						$this -> ExtractTextData ( $object_number, $decoded_stream_data, $remainder, $header, $footer ) ;

						// We still need to check again that the extracted text portion contains something useful
						if  ( $this -> IsText ( $object_data, $remainder ) )
						   {
							$text [ $object_number ]	=  
							$text_data			=  $remainder ;
						    }
					    }
				    }
				else
				   {
					$text [ $object_number ]	=  
					$text_data			=  $decoded_stream_data ;
				    }
					

				// The current object may be a text object that have been defined as an XObject in some other object
				// In this case, we have to keep it since it may be referenced by a /TPLx construct from within 
				// another text object
				if  ( $text_data )
					$this -> PageMap -> AddTemplateObject ( $object_number, $text_data ) ;
			    }
			// This may be here the opportunity to look into the $FormData property and replace object ids with their corresponding data
			else
			   {
				$found		=  false ;

				foreach  ( $this -> FormData  as  &$form_entry )
				   {
					if  ( is_integer ( $form_entry [ 'values' ] )  &&  $object_number  ==  $form_entry [ 'values' ] )
					   {
						$form_entry [ 'values' ]	=  $decoded_stream_data ;
						$found				=  true ;
					    }
					else if  ( is_integer ( $form_entry [ 'form' ] )  &&  $object_number  ==  $form_entry [ 'form' ] )
					   {
						$form_entry [ 'form' ]	=  $decoded_stream_data ;
						$found				=  true ;
					    }
				    }

				if  ( ! $found  &&  self::$DEBUG  >  1 )
					echo "\n----------------------------------- UNRECOGNIZED #$object_number :\n$decoded_stream_data\n" ;
			    }
		    }

		// Form data object numbers
		$this -> FormDataObjectNumbers	=  array_keys ( $this -> FormData ) ;

		// Associate character maps with declared fonts
		foreach  ( $cmaps  as  $cmap )
			$this -> FontTable -> AddCharacterMap ( $cmap ) ;

		// Current font defaults to -1, which means : take the first available font as the current one.
		// Sometimes it may happen that text drawing instructions do not set a font at all (PdfPro for example)
		$current_font		=  -1 ;

		// Build the page catalog
		$this -> Pages	=  array ( ) ;
		$this -> PageMap -> MapObjects ( $text ) ;

		// Add font mappings local to each page
		$mapped_fonts	=  $this -> PageMap -> GetMappedFonts ( ) ;
		$this -> FontTable -> AddPageFontMap ( $mapped_fonts ) ;

		// Extract text from the collected text elements
		foreach ( $this -> PageMap -> Pages as  $page_number => $page_objects )
		   {
			// Checks if this page is selected
			if  ( ! $this -> IsPageSelected ( $page_number ) )
				continue ;
				
			$this -> Pages [ $page_number ]		=  '' ;

			if  ( $layout_option  ===  self::PDFOPT_RAW_LAYOUT )
			   {
				foreach  ( $page_objects  as  $page_object ) 
				   {
					if  ( isset ( $text [ $page_object ] ) )
					   {
						$new_text				 =  $this -> PageMap -> ProcessTemplateReferences ( $page_number, $text [ $page_object ] ) ;
						$object_text				 =  $this -> ExtractText ( $page_number, $page_object, $new_text, $current_font ) ;
						$this -> Pages [ $page_number ]		.=  $object_text ;
					    }
					else if  ( self::$DEBUG  >  1 )
						echo "\n----------------------------------- MISSING OBJECT #$page_object for page #$page_number\n" ;
				    }
			     }
			// New style (basic) layout rendering
			else if  ( $layout_option  ===  self::PDFOPT_BASIC_LAYOUT )
			   {
				$page_fragments		=  array ( ) ;

				foreach  ( $page_objects  as  $page_object ) 
				   {
					if  ( isset ( $text [ $page_object ] ) )
					   {
						$new_text				 =  $this -> PageMap -> ProcessTemplateReferences ( $page_number, $text [ $page_object ] ) ;
						$this -> ExtractTextWithLayout ( $page_fragments, $page_number, $page_object, $new_text, $current_font ) ;
					    }
					else if  ( self::$DEBUG  >  1 )
						echo "\n----------------------------------- MISSING OBJECT #$page_object for page #$page_number\n" ;
				    }

				$this -> Pages [ $page_number ]			=  $this -> __assemble_text_fragments ( $page_number, $page_fragments, $page_width, $page_height ) ;

				$this -> DocumentFragments [ $page_number ]	=  array 
				   (
					'fragments'		=>  $page_fragments,
					'page-width'		=>  $page_width,
					'page_height'		=>  $page_height 
				    ) ;
			    }
		    }

		// Retrieve author information
		if  ( $this -> GotAuthorInformation )
			$this -> RetrieveAuthorInformation ( $author_information_object_id, $pdf_objects ) ;

		// Build the page locations (ie, starting and ending offsets)
		$offset			=  0 ;
		$page_separator		=  utf8_encode ( $this -> PageSeparator ) ;
		$page_separator_length	=  strlen ( $page_separator ) ;

		foreach  ( $this -> Pages  as  $page_number => &$page )
		   {
			// If hyphenated words are unwanted, then remove them
			if  ( $this -> Options &  self::PDFOPT_NO_HYPHENATED_WORDS )
				$page	=  preg_replace ( self::$RemoveHyphensRegex, '$4$2', $page ) ;

			$length					 =  strlen ( $page ) ;
			$this -> PageLocations [ $page_number ]	 =  array ( 'start' => $offset, 'end' => $offset + $length - 1 ) ;
			$offset					+=  $length + $page_separator_length ;
		    }

		// And finally, the Text property
		$this -> Text	=  implode ( $page_separator, $this -> Pages ) ;

		// Free memory
		$this -> MapIdBuffer			=  array ( ) ;
		$this -> RtlCharacterBuffer		=  array ( ) ;
		$this -> CharacterMapBuffer		=  array ( ) ;

		// Compute memory occupied for this file
		$memory_usage_end		=  ( self::$HasMemoryGetUsage     ) ?  memory_get_usage      ( true ) : 0 ;
		$memory_peak_usage_end		=  ( self::$HasMemoryGetPeakUsage ) ?  memory_get_peak_usage ( true ) : 0 ;

		$this -> MemoryUsage		=  $memory_usage_end      - $this -> __memory_usage_start ;
		$this -> MemoryPeakUsage	=  $memory_peak_usage_end - $this -> __memory_peak_usage_start ;

		// Adjust the "Distributions" statistics
		if  ( $this -> Options  &  self::PDFOPT_ENHANCED_STATISTICS )
		   {
			$instruction_count		=  0 ;
			$statistics			=  array ( ) ;

			// Count the total number of instructions 
			foreach  ( $this -> Statistics [ 'Distributions' ]  as  $count )
				$instruction_count  +=  $count ;

			// Now transform the Distributions entries into an associative array containing the instruction counts
			// ('count') and their relative percentage
			foreach  ( $this -> Statistics [ 'Distributions' ]  as  $name => $count )
			   {
				if  ( $instruction_count ) 
					$percent	=  round ( ( 100.0 / $instruction_count ) * $count, 2 ) ;
				else
					$percent	=  0 ;

				$statistics [ $name ]	=  array
				   (
					'instruction'		=>  $name,
					'count'			=>  $count,
					'percent'		=>  $percent 
				    ) ;
			    }

			// Set the new 'Distributions' array and sort it by instruction count in reverse order
			$this -> Statistics [ 'Distributions' ]		=  $statistics ;
			uksort ( $this -> Statistics [ 'Distributions' ], array ( $this, '__sort_distributions' ) ) ;
		    }

		// All done, return
		return ( $this -> Text ) ;
	    }


	public function  __sort_distributions ( $a, $b )
	   { return ( $this -> Statistics [ 'Distributions' ] [$b] [ 'count' ] - $this -> Statistics [ 'Distributions' ] [$a] [ 'count' ] ) ; }



	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        AddAdobeExtraMappings - Adds extra mappings for standard Adobe fonts.
	
	    PROTOTYPE
	        $pdf -> AddAdobeExtraMappings ( $mappings ) ;
	
	    DESCRIPTION
	        Adobe supports 4 predefined fonts : standard, Mac, WinAnsi and PDF). All the characters in these fonts
		are identified by a character time, a little bit like HTML entities ; for example, 'one' will be the
		character '1', 'acircumflex' will be 'â', etc.
		There are thousands of character names defined by Adobe (see https://mupdf.com/docs/browse/source/pdf/pdf-glyphlist.h.html).
		Some of them are not in this list ; this is the case for example of the 'ax' character names, where 'x'
		is a decimal number. When such a character is specified in a /Differences array, then there is somewhere
		a CharProc[] array giving an object id for each of those characters.
		The referenced object(s) in turn contain drawing instructions to draw the glyph. At no point you could 
		guess what is the corresponding Unicode character for this glyph, since the information is not contained
		in the PDF file.
		The AddAdobeExtraMappings() method allows you to specify such correspondences. Specify an array as the
		$mappings parameter, whose keys are the Adobe character name (for example, "a127") and values the 
		corresponding Unicode values (see the description of the $mappings parameter for more information).
	
	    PARAMETERS
	        $mappings (associative array) -
	                Associative array whose keys are Adobe character names. The array values can take several forms :
			- A character
			- An integer value
			- An array of up to four character or integer values.
			Internally, every specified value is converted to an array of four integer values, one for
			each of the standard Adobe character sets (Standard, Mac, WinAnsi and PDF). The following
			rules apply :
			- If the input value is a single character, the output array corrsponding the Adobe character
			  name will be a set of 4 elements corresponding to the ordinal value of the supplied
			  character.
			- If the input value is an integer, the output array will be a set of 4 identical values
			- If the input value is an array :
			  . Arrays with less that 4 elements will be padded, using the last array item for padding
			  . Arrays with more than 4 elements will be silently truncated
			  . Each array value can either be a character or a numeric value.
	
	    NOTES
	        In this current implementation, the method applies the mappings to ALL Adobe default fonts. That is,
		you cannot have one mapping for one Adobe font referenced in the PDF file, then a second mapping for
		a second Adobe font, etc.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  AddAdobeExtraMappings ( $mappings )
	   {
		// Loop through each mapping
		foreach  ( $mappings  as  $key => $value )
		   {
			// Character value : we retain its ordinal value as the 4 values of the output array
			if  ( is_string ( $value ) )
			   {
				$ord		=  ord ( $value ) ;
				$items		=  array ( $ord, $ord, $ord, $ord ) ;
			    }
			// Numeric value : the output array will contain 4 times the supplied value
			else if  ( is_numeric ( $value ) )
			   {
				$value		=  ( integer ) $value ;
				$items		=  array ( $value, $value, $value, $value ) ;
			    }
			// Array value : make sure we will have an output array of 4 values
			else if  ( is_array ( $value ) )
			   {
				$items		=  array ( ) ;

				// Collect the supplied values, converting characters to their ordinal values if necessary
				for  ( $i = 0, $count = count ( $value ) ;  $i  <  $count  &&  $i  <  4 ; $i ++ )
				   {
					$code	=  $value [$i] ;

					if  ( is_string ( $code ) )
						$items []	=  ord ( $code ) ;
					else
						$items []	=  ( integer ) $code ;
				    }

				// Ensure that we have 4 values ; fill the missing ones with the last seen value if necessary
				$count		=  count ( $items ) ;

				if  ( ! $count ) 
					error ( new PdfToTextException ( "Adobe extra mapping \"$key\" has no values." ) ) ;

				$last_value		=  $items [ $count - 1 ] ;

				for  ( $i = $count ; $i  <  4 ; $i ++ )
					$items []	=  $last_value ;
			    }
			else
				error ( new PdfToTextException ( "Invalid value \"$value\" for Adobe extra mapping \"$key\"." ) ) ;

			// Add this current mapping to the Adobe extra mappings array
			$this -> AdobeExtraMappings [ $key ]	=  $items ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetPageFromOffset - Returns a page number from a text offset.
	
	    PROTOTYPE
	        $offset		=  $pdf -> GetPageFromOffset ( $offset ) ;
	
	    DESCRIPTION
	        Given a byte offset in the Text property, returns its page number in the pdf document.
	
	    PARAMETERS
	        $offset (integer) -
	                Offset, in the Text property, whose page number is to be retrieved.
	
	    RETURN VALUE
	        Returns a page number in the pdf document, or false if the specified offset does not exist.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetPageFromOffset ( $offset )
	   {
		if  ( $offset  ===  false ) 
			return ( false ) ;

		foreach  ( $this -> PageLocations  as  $page => $location )
		   {
			if  ( $offset  >=  $location [ 'start' ]  &&  $offset  <=  $location [ 'end' ] )
				return ( $page ) ;
		    }

		return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        text_strpos, text_stripos - Search for an occurrence of a string.
	
	    PROTOTYPE
	        $result		=  $pdf -> text_strpos  ( $search, $start = 0 ) ;
	        $result		=  $pdf -> text_stripos ( $search, $start = 0 ) ;
	
	    DESCRIPTION
	        These methods behave as the strpos/stripos PHP functions, except that :
		- They operate on the text contents of the pdf file (Text property)
		- They return an array containing the page number and text offset. $result [0] will be set to the page
		  number of the searched text, and $result [1] to its offset in the Text property
	
	    PARAMETERS
	        $search (string) -
	                String to be searched.

		$start (integer) -
			Start offset in the pdf text contents.
	
	    RETURN VALUE
	        Returns an array of two values containing the page number and text offset if the searched string has
		been found, or false otherwise.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  text_strpos ( $search, $start = 0 )
	   {
		$offset		=  mb_strpos ( $this -> Text, $search, $start, 'UTF-8' ) ;

		if  ( $offset  !==  false )
			return ( array ( $this -> GetPageFromOffset ( $offset ), $offset ) ) ;

		return ( false ) ;
	    }


	public function  text_stripos ( $search, $start = 0 )
	   {
		$offset		=  mb_stripos ( $this -> Text, $search, $start, 'UTF-8' ) ;

		if  ( $offset  !==  false )
			return ( array ( $this -> GetPageFromOffset ( $offset ), $offset ) ) ;

		return ( false ) ;
	    }




	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        document_strpos, document_stripos - Search for all occurrences of a string.
	
	    PROTOTYPE
	        $result		=  $pdf -> document_strpos  ( $search, $group_by_page = false ) ;
	        $result		=  $pdf -> document_stripos ( $search, $group_by_page = false ) ;
	
	    DESCRIPTION
		Searches for ALL occurrences of a given string in the pdf document. The value of the $group_by_page
		parameter determines how the results are returned :
		- When true, the returned value will be an associative array whose keys will be page numbers and values
		  arrays of offset of the found string within the page
		- When false, the returned value will be an array of arrays containing two entries : the page number
		  and the text offset.

		For example, if a pdf document contains the string "here" at character offset 100 and 200 in page 1, and
		position 157 in page 3, the returned value will be :
		- When $group_by_page is false :
			[ [ 1, 100 ], [ 1, 200 ], [ 3, 157 ] ]
		- When $group_by_page is true :
			[ 1 => [ 100, 200 ], 3 => [ 157 ] ]
	
	    PARAMETERS
	        $search (string) -
	                String to be searched.

		$group_by_page (boolean) -
			Indicates whether the found offsets should be grouped by page number or not.
	
	    RETURN VALUE
	        Returns an array of page numbers/character offsets (see Description above) or false if the specified
		string does not appear in the document.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  document_strpos ( $text, $group_by_page = false )
	   {
		$length		=  strlen ( $text ) ;

		if  ( ! $length )
			return ( false ) ;

		$result		=  array ( ) ;
		$index		=  0 ;

		while ( ( $index =  mb_strpos ( $this -> Text, $text, $index, 'UTF-8' ) )  !==  false )
		   {
			$page	=  $this -> GetPageFromOffset ( $index ) ;

			if  ( $group_by_page )
				$result [ $page ] []	=  $index ;
			else
				$result []		=  array ( $page, $index ) ;

			$index	+=  $length ;
		    }

		return ( $result ) ;
	    }


	public function  document_stripos ( $text, $group_by_page = false )
	   {
		$length		=  strlen ( $text ) ;

		if  ( ! $length )
			return ( false ) ;

		$result		=  array ( ) ;
		$index		=  0 ;

		while ( ( $index =  mb_stripos ( $this -> Text, $text, $index, 'UTF-8' ) )  !==  false )
		   {
			$page	=  $this -> GetPageFromOffset ( $index ) ;

			if  ( $group_by_page )
				$result [ $page ] []	=  $index ;
			else
				$result []		=  array ( $page, $index ) ;

			$index	+=  $length ;
		    }

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        text_match, document_match - Search string using regular expressions.
	
	    PROTOTYPE
	        $status		=  $pdf -> text_match ( $pattern, &$match = null, $flags = 0, $offset = 0 ) ;
	        $status		=  $pdf -> document_match ( $pattern, &$match = null, $flags = 0, $offset = 0 ) ;
	
	    DESCRIPTION
	        text_match() calls the preg_match() PHP function on the pdf text contents, to locate the first occurrence
		of text that matches the specified regular expression.
		document_match() calls the preg_match_all() function to locate all occurrences that match the specified
		regular expression.
		Note that both methods add the PREG_OFFSET_CAPTURE flag when calling preg_match/preg_match_all so you 
		should be aware that all captured results are an array containing the following entries :
		- Item [0] is the captured string
		- Item [1] is its text offset
		- The text_match() and document_match() methods add an extra array item (index 2), which contains the
		  page number where the matched text resides
	
	    PARAMETERS
	        $pattern (string) -
	                Regular expression to be searched.

		$match (any) -
			Output captures. See preg_match/preg_match_all.

		$flags (integer) -
			PCRE flags. See preg_match/preg_match_all.

		$offset (integer) -
			Start offset. See preg_match/preg_match_all.
	
	    RETURN VALUE
	        Returns the number of matched occurrences, or false if the specified regular expression is invalid.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  text_match ( $pattern, &$match = null, $flags = 0, $offset = 0 )
	   {
		$local_match	=  null ;
		$status		=  preg_match ( $pattern, $this -> Text, $local_match, $flags | PREG_OFFSET_CAPTURE, $offset ) ;

		if  ( $status ) 
		   {
			foreach  ( $local_match  as  &$entry )
				$entry [2]	=  $this -> GetPageFromOffset ( $entry [1] ) ;

			$match	=  $local_match ;
		    }

		return ( $status ) ;
	    }


	public function  document_match ( $pattern, &$matches = null, $flags = 0, $offset = 0 )
	   {
		$local_matches	=  null ;
		$status		=  preg_match_all ( $pattern, $this -> Text, $local_matches, $flags | PREG_OFFSET_CAPTURE, $offset ) ;

		if  ( $status ) 
		   {
			foreach  ( $local_matches  as  &$entry )
			   {
				foreach  ( $entry  as  &$subentry )
				$subentry [2]	=  $this -> GetPageFromOffset ( $subentry [1] ) ;
			    }

			$matches	=  $local_matches ;
		    }

		return ( $status ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    HasFormData -
		Returns true if the PDF file contains form data or not.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  HasFormData ( )
	   {
		return ( count ( $this -> FormData )  >  0 ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    GetFormCount -
		Returns the number of top-level forms contained in the PDF file.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetFormCount ( )
	   {
		return ( count ( $this -> FormData ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetFormData - Returns form data, if any
	
	    PROTOTYPE
	        $object		=  $pdf -> GetFormData ( $template = null, $form_index = 0 ) ;
	
	    DESCRIPTION
	        Retrieves form data if present.
	
	    PARAMETERS
	        $template (string) -
	                An XML file describing form data using human-readable names for field values.
			If not specified, the inline form definitions will be used, together with the field names 
			specified in the PDF file.

		$form_index (integer) -
			Form index in the PDF file. So far, I really don't know if a PDF file can have multiple forms.
	
	    RETURN VALUE
	        An object derived from the PdfToTextFormData class.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetFormData ( $template = null, $form_index = 0 )
	   {
		if  ( isset ( $this -> FormDataObjects [ $form_index ] ) )
			return ( $this -> FormDataObjects [ $form_index ] ) ;

		if  ( $form_index  >  count ( $this -> FormDataObjectNumbers ) )
			error ( new PdfToTextFormException ( "Invalid form index #$form_index." ) ) ;

		$form_data	=  $this -> FormData [ $this -> FormDataObjectNumbers [ $form_index ] ] ;

		if  ( $template )
		   {
			if  ( ! file_exists ( $template ) )
				error ( new PdfToTextFormException ( "Form data template file \"$template\" not found." ) ) ;

			$xml_data	=  file_get_contents ( $template ) ;  
			$definitions	=  new PdfToTextFormDefinitions ( $xml_data, $form_data [ 'form' ] ) ; ;
		    }
		else
		   {
			$definitions	=  new PdfToTextFormDefinitions ( null, $form_data [ 'form' ] ) ;
		    }

		$object		=  $definitions [ $form_index ]	-> GetFormDataFromPdfObject ( $form_data [ 'values' ] ) ;

		$this -> FormDataDefinitions []		=  $definitions ;
		$this -> FormDataObjects []		=  $object ;

		return ( $object ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        MarkTextLike - Marks output text.
	
	    PROTOTYPE
	        $pdf -> MarkTextLike ( $regex, $marker_start, $marker_end ) ;
	
	    DESCRIPTION
	        Sometimes it may be convenient, when you want to extract only a portion of text, to say : "I want to
		extract text between this title and this title". The MarkTextLike() method provides some support for
		such a task. Imagine you have documents that have the same structure, all starting with an "Introduction"
		title :

			Introduction
				...
				some text
				...
			Some other title
				...

		By calling the MarkTextLike() method such as in the example below :

			$pdf -> MarkTextLike ( '/\bIntroduction\b/', '<M>', '</M' ) ;

		then you will get as output :

			<M>Introduction</M>
				...
				some text
				...
			<M>Some other title</M>

		Adding such markers in the output will allow you to easily extract the text between the chapters
		"Introduction" and "Some other title", using a regular expression.

		The font name used for the first string matched by the specified regular expression will be searched
		later to add markers around all the text portions using this font.

	
	    PARAMETERS
	        $regex (string) -
	                A regular expression to match the text to be matched. Subsequent portions of text using the
			same font will be surrounded by the marker start/end strings.

		$marker_start, $marker_end (string) -
			Markers to surround the string when a match is found.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  MarkTextLike ( $regex, $marker_start, $marker_end )
	   {
		$this -> UnprocessedMarkerList [ 'font' ] []	=  array
		   (
			'regex'		=>  $regex,
			'start'		=>  $marker_start,
			'end'		=>  $marker_end
		    ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        SetCaptures, SetCapturesFromString - Defines document parts to be captured.
	
	    PROTOTYPE
	        $pdf -> SetCaptures ( $xml_file ) ;
		$pdf -> SetCapturesFromString ( $xml_data ) ;
	
	    DESCRIPTION
	        Defines document parts to be captured.
		SetCaptures() takes the definitions for the areas to be captured from an XML file, while
		SetCapturesFromString() takes them from a string representing xml capture definitions.
	
	    NOTES
	        - See file README.md for an explanation on the format of the XML capture definition file.
		- The SetCaptures() methods must be called before the Load() method.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetCaptures ( $xml_file )
	   {
		if  ( ! file_exists ( $xml_file ) )
			error ( new PdfToTextException ( "File \"$xml_file\" does not exist." ) ) ;

		$xml_data	=  file_get_contents ( $xml_file ) ;

		$this -> SetCapturesFromString ( $xml_data )  ;

	    }


	public function  SetCapturesFromString ( $xml_data )
	   {
		// Setting capture areas implies having the PDFOPT_BASIC_LAYOUT option
		$this -> Options	|=  self::PDFOPT_BASIC_LAYOUT ;

		$this -> CaptureDefinitions		=  new PdfToTextCaptureDefinitions ( $xml_data ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetCaptures - Returns captured data.
	
	    PROTOTYPE
	        $object		=  $pdf -> GetCaptures ( $full = false ) ;
	
	    PARAMETERS 
		$full (boolean) -
			When true, the whole captures, togethers with their definitions, are returned. When false,
			only a basic object containing the capture names and their values is returned.

	    DESCRIPTION
	        Returns the object that contains captured data.
	
	    RETURN VALUE
	        An object of type PdfToTextCaptures, or false if an error occurred.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetCaptures ( $full = false )
	   {
		if  ( ! $this -> CaptureObject )
		   {
			$this -> CaptureDefinitions -> SetPageCount ( count ( $this -> Pages ) ) ;
			$this -> CaptureObject	=  $this -> CaptureDefinitions -> GetCapturedObject ( $this -> DocumentFragments ) ;
		    }

		if  ( $full ) 
			return ( $this -> CaptureObject ) ;
		else 
			return ( $this -> CaptureObject -> ToCaptures ( ) ) ;
	    }


	/**************************************************************************************************************
	 **************************************************************************************************************
	 **************************************************************************************************************
	 ******                                                                                                  ******
	 ******                                                                                                  ******
	 ******                                         INTERNAL METHODS                                         ******
	 ******                                                                                                  ******
	 ******                                                                                                  ******
	 **************************************************************************************************************
	 **************************************************************************************************************
	 **************************************************************************************************************/

	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        AddImage - Adds an image from the PDF stream to the current object.
	
	    PROTOTYPE
	        $this -> AddImage ( $object_id, $stream_data, $type, $object_data ) ;
	
	    DESCRIPTION
	        Adds an image from the PDF stream to the current object.
		If the PDFOPT_GET_IMAGE_DATA flag is enabled, image data will be added to the ImageData property.
		If the PDFOPT_DECODE_IMAGE_DATA flag is enabled, a jpeg resource will be created and added into the
		Images array property.
	
	    PARAMETERS
	        $object_id (integer) -
	                Pdf object id.

		$stream_data (string) -
			Contents of the unprocessed stream data containing the image.

		$type (integer) -
			One of the PdfToText::PDF_*_ENCODING constants.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  AddImage ( $object_id, $stream_data, $type, $object_data )
	   {

		if  ( self::$DEBUG  &&   $this -> Options  &  self::PDFOPT_GET_IMAGE_DATA )
		    {
			switch  ( $type )  
			   {
				case	self::PDF_DCT_ENCODING :
					$this -> ImageData	=  array ( 'type' => 'jpeg', 'data' => $stream_data ) ;
					break ;
			    }

		     }


		if  ( $this -> Options  &  self::PDFOPT_DECODE_IMAGE_DATA  &&
			( ! $this -> MaxExtractedImages  ||  $this -> ImageCount  <  $this -> MaxExtractedImages ) )
		   {
			$image	=  $this -> DecodeImage ( $object_id, $stream_data, $type, $object_data, $this -> Options  &  self::PDFOPT_AUTOSAVE_IMAGES ) ;

			if  ( $image  !==  false )
			   {
				$this -> ImageCount ++ ;

				// When the PDFOPT_AUTOSAVE_IMAGES flag is set, we simply use a template filename to generate a real output filename
				// then save the image to that file. The memory is freed after that.
				if  ( $this -> Options  &  self::PDFOPT_AUTOSAVE_IMAGES )
				   {
					$output_filename 			=  $this -> __get_output_image_filename ( ) ;

					$image -> SaveAs ( $output_filename, $this -> ImageAutoSaveFormat ) ;
					unset ( $image ) ;
					
					$this -> AutoSavedImageFiles []		=  $output_filename ;
				    }
				// Otherwise, simply store the image data into memory
				else
					$this -> Images []	=  $image ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
	        DecodeData - Decodes stream data.

	    PROTOTYPE
	        $data	=  $this -> DecodeData ( $object_id, $stream_data, $type ) ;

	    DESCRIPTION
	        Decodes stream data (binary data located between the "stream" and "enstream" directives) according to the
		specified encoding type, given in the surrounding object parameters.

	    PARAMETERS
		$object_id (integer) -
			Id of the object containing the data.

	        $stream_data (string) -
	                Contents of the binary stream.

		$type (integer) -
			One of the PDF_*_ENCODING constants, as returned by the GetEncodingType() method.

	    RETURN VALUE
	        Returns the decoded stream data.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  DecodeData ( $object_id, $stream_data, $type, $object_data )
	   {
		$decoded_stream_data 	=  '' ;

		switch  ( $type )
		   {
		   	case 	self::PDF_FLATE_ENCODING :
				// Objects in password-protected Pdf files SHOULD be encrypted ; however, it happens that we may encounter normal,
				// unencrypted ones. This is why we always try to gzuncompress them first then, if failed, try to decrypt them
		   		$decoded_stream_data 	=  @gzuncompress ( $stream_data ) ;

				if  ( $decoded_stream_data  ===  false )
				   {
					if  ( $this -> IsEncrypted )
					   {
						$decoded_stream_data	=  $this -> EncryptionData -> Decrypt ( $object_id, $stream_data ) ;

						if  ( $decoded_stream_data  ===  false )
						   {
							if  ( self::$DEBUG  >  1 )
								warning ( new PdfToTextDecodingException ( "Unable to decrypt object contents.", $object_id ) ) ;
						    }
					    }
					else if  ( self::$DEBUG  >  1 )
						warning ( new PdfToTextDecodingException ( "Invalid gzip data.", $object_id ) ) ;
				    }

		   		break ;

			case	self::PDF_LZW_ENCODING :
				$decoded_stream_data	=  $this -> __decode_lzw ( $stream_data ) ;
				break ;

		   	case 	self::PDF_ASCIIHEX_ENCODING :
		   		$decoded_stream_data 	=  $this -> __decode_ascii_hex ( $stream_data ) ;
		   		break ;

			case 	self::PDF_ASCII85_ENCODING :
				$decoded_stream_data 	=  $this -> __decode_ascii_85 ( $stream_data ) ;

				// Dumbly check if this could not be gzipped data after decoding (normally, the object flags should also specify
				// the /FlateDecode flag) 
				if  ( $decoded_stream_data  !==  false  &&  ( $result = @gzuncompress ( $decoded_stream_data ) )  !==  false )
					$decoded_stream_data  =  $result ;

				break ;

			case	self::PDF_TEXT_ENCODING :
				$decoded_stream_data	=  $stream_data ;
				break ;
		    }

		return ( $decoded_stream_data ) ;
	    }


	// __decode_lzw -
	//	Decoding function for LZW encrypted data. This function is largely inspired by the TCPDF one but has been rewritten
	//	for a performance gain of 30-35%.
	private function   __decode_lzw ( $data ) 
	   {
		// The initial dictionary contains 256 entries where each index is equal to its character representation
		static $InitialDictionary      =  array
		   (
			"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
			"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
			"\x20", "\x21", "\x22", "\x23", "\x24", "\x25", "\x26", "\x27", "\x28", "\x29", "\x2A", "\x2B", "\x2C", "\x2D", "\x2E", "\x2F",
			"\x30", "\x31", "\x32", "\x33", "\x34", "\x35", "\x36", "\x37", "\x38", "\x39", "\x3A", "\x3B", "\x3C", "\x3D", "\x3E", "\x3F",
			"\x40", "\x41", "\x42", "\x43", "\x44", "\x45", "\x46", "\x47", "\x48", "\x49", "\x4A", "\x4B", "\x4C", "\x4D", "\x4E", "\x4F",
			"\x50", "\x51", "\x52", "\x53", "\x54", "\x55", "\x56", "\x57", "\x58", "\x59", "\x5A", "\x5B", "\x5C", "\x5D", "\x5E", "\x5F",
			"\x60", "\x61", "\x62", "\x63", "\x64", "\x65", "\x66", "\x67", "\x68", "\x69", "\x6A", "\x6B", "\x6C", "\x6D", "\x6E", "\x6F",
			"\x70", "\x71", "\x72", "\x73", "\x74", "\x75", "\x76", "\x77", "\x78", "\x79", "\x7A", "\x7B", "\x7C", "\x7D", "\x7E", "\x7F",
			"\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88", "\x89", "\x8A", "\x8B", "\x8C", "\x8D", "\x8E", "\x8F",
			"\x90", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9A", "\x9B", "\x9C", "\x9D", "\x9E", "\x9F",
			"\xA0", "\xA1", "\xA2", "\xA3", "\xA4", "\xA5", "\xA6", "\xA7", "\xA8", "\xA9", "\xAA", "\xAB", "\xAC", "\xAD", "\xAE", "\xAF",
			"\xB0", "\xB1", "\xB2", "\xB3", "\xB4", "\xB5", "\xB6", "\xB7", "\xB8", "\xB9", "\xBA", "\xBB", "\xBC", "\xBD", "\xBE", "\xBF",
			"\xC0", "\xC1", "\xC2", "\xC3", "\xC4", "\xC5", "\xC6", "\xC7", "\xC8", "\xC9", "\xCA", "\xCB", "\xCC", "\xCD", "\xCE", "\xCF",
			"\xD0", "\xD1", "\xD2", "\xD3", "\xD4", "\xD5", "\xD6", "\xD7", "\xD8", "\xD9", "\xDA", "\xDB", "\xDC", "\xDD", "\xDE", "\xDF",
			"\xE0", "\xE1", "\xE2", "\xE3", "\xE4", "\xE5", "\xE6", "\xE7", "\xE8", "\xE9", "\xEA", "\xEB", "\xEC", "\xED", "\xEE", "\xEF",
			"\xF0", "\xF1", "\xF2", "\xF3", "\xF4", "\xF5", "\xF6", "\xF7", "\xF8", "\xF9", "\xFA", "\xFB", "\xFC", "\xFD", "\xFE", "\xFF"
		    ) ;

		// Dictionary lengths - when we reach one of the values specified as the key, we have to set the bit length to the corresponding value
		static  $DictionaryLengths	=  array 
		   (
			511		=>  10,
			1023		=>  11,
			2047		=>  12
		    ) ;

		// Decoded string to be returned
		$result		=  '' ;

		// Convert string to binary string
		$bit_string	=  '' ;
		$data_length	=  strlen ( $data ) ;

		for  ( $i = 0 ; $i  <  $data_length ; $i ++ ) 
			$bit_string	.=  sprintf ( '%08b', ord ( $data[$i] ) ) ;

		$data_length	*=  8 ;
		
		// Initialize dictionary
		$bit_length		=  9 ;
		$dictionary_index	=  258 ;
		$dictionary		=  $InitialDictionary ;

		// Previous value
		$previous_index		=  0 ;

		// Start index in bit string
		$start_index		=  0 ;

		// Until we encounter the EOD marker (257), read $bit_length bits
		while  ( ( $start_index  <  $data_length )  &&  ( ( $index = bindec ( substr ( $bit_string, $start_index, $bit_length ) ) )  !==  257 ) ) 
		   {
			// Move to next bit position
			$start_index	+=  $bit_length ;

			if  ( $index  !==  256  &&  $previous_index  !==  256 )
			    {
				// Check if index exists in the dictionary and remember it
				if  ( $index  <  $dictionary_index ) 
				   {
					$result			.=  $dictionary [ $index ] ;
					$dictionary_value	 =  $dictionary [ $previous_index ] . $dictionary [ $index ] [0] ;
					$previous_index		 =  $index ;
				    } 
				// Index does not exist - add it to the dictionary
				else 
				   {
					$dictionary_value	 =  $dictionary [ $previous_index ] . $dictionary [ $previous_index ] [0] ;
					$result			.=  $dictionary_value ;
				    }

				// Update dictionary
				$dictionary [ $dictionary_index ++ ]	=  $dictionary_value ;
				
				// Change bit length whenever we reach an index limit
				if  ( isset ( $DictionaryLengths [ $dictionary_index ] ) )
					$bit_length	=  $DictionaryLengths [ $dictionary_index ] ;
			    }
			// Clear table marker
			else if  ( $index  ===  256) 
			   { 
				// Reset dictionary and bit length
				// Reset dictionary and bit length
				$bit_length		=  9 ;
				$dictionary_index	=  258 ;
				$previous_index		=  256 ;
				$dictionary		=  $InitialDictionary ;
			     }
			// First entry
			else	// $previous_index  === 256 
			   {
				// first entry
				$result		.=  $dictionary [ $index ] ;
				$previous_index  =  $index ;
			    } 
		    }

		// All done, return
		return ( $result ) ;
	   }


	// __decode_ascii_hex -
	//	Decoder for /AsciiHexDecode streams.
	private function __decode_ascii_hex ( $input )
	    {
	    	$output 	=  "" ;
	    	$is_odd 		=  true ;
	    	$is_comment 	=  false ;

	    	for  ( $i = 0, $codeHigh =  -1 ; $i  <  strlen ( $input )  &&  $input [ $i ]  !=  '>' ; $i++ )
	    	   {
	    		$c 	=  $input [ $i ] ;

	    		if  ( $is_comment )
	    		   {
	    			if   ( $c  ==  '\r'  ||  $c  ==  '\n' )
	    				$is_comment 	=  false ;

	    			continue;
	    		    }

	    		switch  ( $c )
	    		   {
	    			case  '\0' :
	    			case  '\t' :
	    			case  '\r' :
	    			case  '\f' :
	    			case  '\n' :
	    			case  ' '  :
	    				break ;

	    			case '%' :
	    				$is_comment 	=  true ;
	    				break ;

	    			default :
	    				$code 	=  hexdec ( $c ) ;

	    				if  ( $code  ===  0  &&  $c  !=  '0' )
	    					return ( '' ) ;

	    				if  ( $is_odd )
	    					$codeHigh 	 =  $code ;
					else
	    					$output 	.=  chr ( ( $codeHigh << 4 ) | $code ) ;

	    				$is_odd 	=  ! $is_odd ;
	    				break ;
	    		    }
	    	    }

	    	if  ( $input [ $i ]  !=  '>' )
	    		return ( '' ) ;

	    	if  ( $is_odd )
	    		$output 	.=  chr ( $codeHigh << 4 ) ;

	    	return ( $output ) ;
	    }


	// __decode_ascii_85 -
	//	Decoder for /Ascii85Decode streams.
	private function  __decode_ascii_85 ( $data )
	   {
		// Ordinal value of the first character used in Ascii85 encoding
		static	$first_ord	=  33 ;
		// "A 'z' in the input data means "sequence of 4 nuls"
		static	$z_exception	=  "\0\0\0\0" ;
		// Powers of 85, from 4 to 0
		static	$exp85		=  array ( 52200625, 614125, 7225, 85, 1 ) ;

		// Ignore empty data
		if  ( $data  ===  '' )
			return ( false ) ;

		$data_length	=  strlen ( $data ) ;
		$ords		=  array ( ) ;
		$ord_count	=  0 ;
		$result		=  '' ;

		// Paranoia : Ascii85 data may start with '<~' (but it always end with '~>'). Anyway, we must start past this construct if present
		if  ( $data [0]  ==  '<'  &&  $data [1]  ==  '~' )
			$start	=  2 ;
		else
			$start	=  0 ;

		// Loop through nput characters
		for  ( $i = $start ; $i  <  $data_length  &&  $data [$i]  !=  '~' ; $i ++ )
		   {
			$ch	=  $data [$i] ;

			// Most common case : current character is in the range of the Ascii85 encoding ('!'..'u')
			if  ( $ch  >=  '!'  &&  $ch  <=  'u' )
				$ords [ $ord_count ++ ]		=  ord ( $ch ) - $first_ord ;
			// 'z' is replaced with a sequence of null bytes
			else if  ( $ch  ==  'z'  &&  ! $ord_count )
				$result		.=  $z_exception ;
			// Spaces are ignored
			else if  ( $ch  !==  "\0"  &&  $ch  !==  "\t"  &&  $ch  !==  ' '  &&  $ch  !==  "\r"  &&  $ch  !==  "\n"  &&  $ch  !==  "\f" )
				continue ;
			// Other characters : corrupted data...
			else
				return ( false ) ;

			// We have collected 5 characters in base 85 : convert their 32-bits value to base 2 (3 characters)
			if  ( $ord_count  ==  5 )
			   {
				$ord_count	=  0 ;

    				for  ( $sum = 0, $j = 0  ; $j  <  5  ; $j ++ )
    					$sum 	=  ( $sum * 85 ) + $ords [ $j ] ;

    				for ( $j = 3  ; $j  >=  0  ; $j -- )
    					$result 	.=  chr ( $sum >> ( $j * 8 ) ) ;
			    }
		    }

		// A last processing for the potential remaining bytes
		// Notes : this situation has never been tested
		if  ( $ord_count )
    		   {
    			for  ( $i = 0, $sum = 0  ; $i  <  $ord_count  ; $i++ )
    				$sum 	+= ( $ords [ $i ] + ( $i == $ord_count - 1 ) ) * $exp85 [$i] ;

    			for  ( $i = 0  ; $i  <  $ord_count - 1  ; $i++ )
    				$result 	.=  chr ( $sum >> ( ( 3 - $i ) * 8 ) ) ;
    		    }

		// All done, return
		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        DecodeImage - Returns decoded image contents.
	
	    PROTOTYPE
	        TBC
	
	    DESCRIPTION
	        description
	
	    PARAMETERS
	        $object_id (integer) -
	                Pdf object number.

		$stream_data (string) -
			Object data.

		$type (integer) -
			One of the PdfToText::PDF_*_ENCODING constants.

		$autosave (boolean) -
			When autosave is selected, images will not be decoded into memory unless they have a format
			different from JPEG. This is intended to save memory.
	
	    RETURN VALUE
	        Returns an object of type PdfIMage, or false if the image encoding type is not currently supported.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  DecodeImage ( $object_id, $stream_data, $type, $object_data, $autosave )
	   {
		switch  ( $type )  
		   {
			// Normal JPEG image
			case	self::PDF_DCT_ENCODING :
				return ( new PdfJpegImage ( $stream_data, $autosave ) ) ;

			// CCITT fax image
			case	self::PDF_CCITT_FAX_ENCODING :
				return ( new PdfFaxImage ( $stream_data ) ) ;

			// For now, I have not found enough information to be able to decode image data in an inflated stream...
			// In some cases, however, this is JPEG data
			case	self::PDF_FLATE_ENCODING :
				$image		=  PdfInlinedImage::CreateInstance ( $stream_data, $object_data, $autosave ) ;

				if  ( $image )
					return ( $image ) ;

				break ;

			default :
				return ( false ) ;
		    }

		return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        DecodeObjectStream - Decodes an object stream.
	
	    PROTOTYPE
	        $array	=  $this -> DecodeObjectStream ( $object_id, $object_data ) ;
	
	    DESCRIPTION
	        Decodes an object stream. An object stream is yet another PDF object type that contains itself several
		objects not defined using the "x y obj ... endobj" syntax.
		As far as I understood, object streams data is contained within stream/endstream delimiters, and is 
		gzipped.
		Object streams start with a set of object id/offset pairs separated by a space ; catenated object data 
		immediately follows the last space ; for example :

			1167 0 1168 114 <</DA(/Helv 0 Tf 0 g )/DR<</Encoding<</PDFDocEncoding 1096 0 R>>/Font<</Helv 1094 0 R/ZaDb 1095 0 R>>>>/Fields[]>>[/ICCBased 1156 0 R]

		The above example specifies two objects :
			. Object #1167, which starts at offset 0 and ends before the second object, at offset #113 in
			  the data. The contents are :
				<</DA(/Helv 0 Tf 0 g )/DR<</Encoding<</PDFDocEncoding 1096 0 R>>/Font<</Helv 1094 0 R/ZaDb 1095 0 R>>>>/Fields[]>>
			. Object #1168, which starts at offset #114 and continues until the end of the object stream.
			  It contains the following data :
				[/ICCBased 1156 0 R]
	
	    PARAMETERS
	        $object_id (integer) -
	                Pdf object number.

		$object_data (string) -
			Object data.
	
	    RETURN VALUE
	        Returns false if any error occurred (mainly for syntax reasons).
		Otherwise, returns an associative array containing the following elements :
		- object_id :
			Array of all the object ids contained in the object stream.
		- object :
			Array of corresponding object data.

		The reason for this format is that it is identical to the array returned by the preg_match() function
		used in the Load() method for finding objects in a PDF file (ie, a regex that matches "x y oj/endobj"
		constructs).
		
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  DecodeObjectStream ( $object_id, $object_data )
	   {
		// Extract gzipped data for this object
		if  ( preg_match ( '#[^/] stream ( (\r? \n) | \r ) (?P<stream> .*?) endstream#imsx', $object_data, $stream_match ) )
		    {
			$stream_data	=  $stream_match [ 'stream' ] ;
			$type 		=  $this -> GetEncodingType ( $object_id, $object_data ) ;
			$decoded_data	=  $this -> DecodeData ( $object_id, $stream_data, $type, $object_data ) ;

			if  ( self::$DEBUG  >  1 )
				echo "\n----------------------------------- OBJSTREAM #$object_id\n$decoded_data" ;
		      }
		// Stay prepared to find one day a sample declared as an object stream but not having gzipped data delimited by stream/endstream tags
		else
		   {
			if  ( self::$DEBUG  >  1 )
				error ( new PdfToTextDecodingException ( "Found object stream without gzipped data", $object_id ) ) ;

			return ( false ) ;
		    }

		// Object streams data start with a series of object id/offset pairs. The offset is absolute to the first character
		// after the last space of these series.
		// Note : on Windows platforms, the default stack size is 1Mb. The following regular expression will make Apache crash in most cases,
		// so you have to enable the following lines in your http.ini file to set a stack size of 8Mb, as for Unix systems :
		//	Include conf/extra/httpd-mpm.conf
		//	ThreadStackSize 8388608
		if  ( ! preg_match ( '/^ \s* (?P<series> (\d+ \s* )+ )/x', $decoded_data, $series_match ) )
		   {
			if  ( self::$DEBUG  >  1 )
				error ( new PdfToTextDecodingException ( "Object stream does not start with integer object id/offset pairs.", $object_id ) ) ;

			return ( false ) ;
		    }

		// Extract the series of object id/offset pairs and the stream object data
		$series		=  explode ( ' ', rtrim ( preg_replace ( '/\s+/', ' ', $series_match [ 'series' ] ) ) ) ;
		$data		=  substr ( $decoded_data, strlen ( $series_match [ 'series' ] ) ) ;

		// $series should contain an even number of values
		if  ( count ( $series ) % 2 )
		   {
			if  ( self::$DEBUG )
				warning ( new PdfToTextDecodingException ( "Object stream should start with an even number of integer values.", $object_id ) ) ;

			array_pop ( $series ) ;
		    }

		// Extract every individual object
		$objects	=  array ( 'object_id' => array ( ), 'object' => array ( ) ) ;

		for  ( $i = 0, $count = count ( $series ) ; $i  <  $count ; $i += 2 )
		   {
			$object_id	=  ( integer ) $series [$i] ;
			$offset		=  ( integer ) $series [$i+1] ;

			// If there is a "next" object, extract only a substring within the object stream contents
			if  ( isset ( $series [ $i + 3 ] ) ) 
				$object_contents	=  substr ( $data, $offset, $series [ $i + 3 ] - $offset ) ;
			// Otherwise, extract everything until the end
			else
				$object_contents	=  substr ( $data, $offset ) ;

			$objects [ 'object_id'] []	=  $object_id ;
			$objects [ 'object'   ] []	=  $object_contents ;
		    }

		return ( $objects ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        ExtractTextData - Extracts text, header & footer information from a text object.
	
	    PROTOTYPE
	        $this -> ExtractTextData ( $object_id, $stream_contents, &$text, &$header, &$footer ) ;
	
	    DESCRIPTION
	        Extracts text, header & footer information from a text object. The extracted text contents will be
		stripped from any header/footer information.
	
	    PARAMETERS
	        $text (string) -
	                Variable that will receive text contents.

		$header, $footer (string) -
			Variables that will receive header and footer information.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  ExtractTextData ( $object_id, $stream_contents, &$text, &$header, &$footer )
	   {
		// Normally, a header or footer is introduced with a construct like :
		//	<< /Type /Pagination ... [/Bottom] ... >> (or [/Top]
		// The initial regular expression was : 
		//	<< .*? \[ \s* / (?P<location> (Bottom) | (Top) ) \s* \] .*? >> \s* BDC .*? EMC
		// (the data contained between the BDC and EMC instructions are text-drawing instructions).
		// However, this expression revealed to be too greedy and captured too much data ; in the following example :
		//	<</MCID 0>> ...(several kb of drawing instructions)... << ... [/Bottom] ... >> BDC (other drawing instructions for the page footer) EMC
		// everything was captured, from the initial "<<M/MCID 0>>" to the final "EMC", which caused regular page contents to be interpreted as page bottom
		// contents.
		// The ".*?" in the regex has been replaced with "[^>]*?", which works better. However, it will fail to recognize header/footer contents if
		// the header/footer declaration contains a nested construct , such as : 
		//	<< /Type /Pagination ... [/Bottom] ... << (some nested contents) >> ... >> (or [/Top]
		// Let's wait for the case to happen one day...
		static		$header_or_footer_re	=  '#
								(?P<contents> 
									<< [^>]*? \[ \s* / (?P<location> (Bottom) | (Top) ) \s* \] [^>]*? >> \s*
									BDC .*? EMC
								 )
							    #imsx' ;

		$header		=
		$footer		=  
		$text		=  '' ;

		if  ( preg_match_all ( $header_or_footer_re, $stream_contents, $matches, PREG_OFFSET_CAPTURE ) )
		   {
			for  ( $i = 0, $count = count ( $matches [ 'contents' ] ) ; $i  <  $count ; $i ++ )
			   {
				if  ( ! strcasecmp ( $matches [ 'location' ] [$i] [0], 'Bottom' ) )
					$footer		=  $matches [ 'contents' ] [$i] [0] ;
				else
					$header		=  $matches [ 'contents' ] [$i] [0] ;
			    }

			$text	=  preg_replace ( $header_or_footer_re, '', $stream_contents ) ;
		    }
		else
			$text	=  $stream_contents ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    NAME
		ExtractText - extracts text from a pdf stream.

	    PROTOTYPE
		$text 	=  $this -> ExtractText ( $page_number, $object_id, $data, &$current_font ) ;

	    DESCRIPTION
	        Extracts text from decoded stream contents.

	    PARAMETERS
		$page_number (integer) -
			¨Page number that contains the text to be extracted.

	    	$object_id (integer) -
	    		Object id of this text block.

	    	$data (string) -
	    		Stream contents.

		$current_font (integer) -
			Id of the current font, which should be found in the $this->FontTable property, if anything
			went ok.
			This parameter is required, since text blocks may not specify a new font resource id and reuse
			the one that waas set before.

	    RETURN VALUE
		Returns the decoded text.

	    NOTES
		The PDF language can be seen as a stack-driven language  ; for example, the instruction defining a text
		matrix ( "Tm" ) expects 6 floating-point values from the stack :

			0 0 0 0 x y Tm

		It can also specify specific operators, such as /Rx, which sets font number "x" to be the current font,
		or even "<< >>" constructs that we can ignore during our process of extracting textual data.
		Actually, we only want to handle a very small subset of the Adobe drawing language ; These are :
		- "Tm" instructions, that specify, among others, the x and y coordinates of the next text to be output
		- "/R" instructions, that specify which font is to be used for the next text output. This is useful
		  only if the font has an associated character map.
		- "/F", same as "/R", but use a font map id instead of a direct object id.
		- Text, specified either using a single notation ( "(sometext)" ) or the array notation
		  ( "[(...)d1(...)d2...(...)]" ), which allows for specifying inter-character spacing.
		 - "Tf" instructions, that specifies the font size. This is to be able to compute approximately the
		   number of empty lines between two successive Y coordinates in "Tm" instructions
		 - "TL" instructions, that define the text leading to be used by "T*"

		This is why I choosed to decompose the process of text extraction into three steps :
		- The first one, the lowest-level step, is a tokenizer that extracts individual elements, such as "Tm",
		  "TJ", "/Rx" or "510.77". This is handled by the __next_token() method.
		- The second one, __next_instruction(), collects tokens. It pushes every floating-point value onto the
		  stack, until an instruction is met.
		- The third one, ExtractText(), processes data returned by __next_instruction(), and actually performs
		  the (restricted) parsing of text drawing instructions.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  ExtractText ( $page_number, $object_id, $data, &$current_font )
	   {
		$new_data	=  $this -> __strip_useless_instructions ( $data ) ;

		if  ( self::$DEBUG )
		   {
			echo "\n----------------------------------- TEXT #$object_id (size = " . strlen ( $data ) . " bytes, new size = " . strlen ( $new_data ) . " bytes)\n" ;
			echo $data ;
			echo "\n----------------------------------- OPTIMIZED TEXT #$object_id (size = " . strlen ( $data ) . " bytes, new size = " . strlen ( $new_data ) . " bytes)\n" ;
			echo $new_data ;
		    }

		$data		=  $new_data ;

		// Index into the specified block of text-drawing instructions
		$data_index 			=  0 ;

		$data_length 			=  strlen ( $data ) ;		// Data length
		$result 			=  '' ;				// Resulting string

		// Y-coordinate of the last seen "Tm" instruction
		$last_goto_y 			=  0 ;
		$last_goto_x			=  0 ;

		// Y-coordinate of the last seen "Td" or "TD" relative positioning instruction
		$last_relative_goto_y		=  0 ;

		// When true, the current text should be output on the same line as the preceding one
		$use_same_line 			=  false ;

		// Instruction preceding the current one
		$last_instruction 		=  true ;

		// Current font size
		$current_font_size 		=  0 ;

		// Active template
		$current_template		=  '' ;

		// Various pre-computed variables
		$separator_length		=  strlen ( $this -> Separator ) ;

		// Current font map width, in bytes, plus a flag saying whether the current font is mapped or not
		$this -> FontTable -> GetFontAttributes ( $page_number, $current_template, $current_font, $current_font_map_width, $current_font_mapped ) ;

		// Extra newlines to add before the current text
		$extra_newlines 		=  0 ;

		// Text leading used by T*
		$text_leading 			=  0 ;

		// Set to true if a separator needs to be inserted
		$needs_separator		=  false ;

		// A flag to tell if we should "forget" the last instruction
		$discard_last_instruction	=  false ;

		// A flag that tells whether the Separator and BlockSeparator properties are identical
		$same_separators		=  ( $this -> Separator  ==  $this -> BlockSeparator ) ;

		// Instruction count (used for handling execution timeouts)
		$instruction_count		=  0 ;

		// Unprocessed markers
		$unprocessed_marker_count	=  count ( $this -> UnprocessedMarkerList [ 'font' ] ) ;

		// Loop through instructions
		while  ( ( $instruction =  $this -> __next_instruction ( $page_number, $data, $data_length, $data_index, $current_template ) )  !==  false )
		   {
			$fragment	=  '' ;

			$instruction_count ++ ;

			// Timeout handling - don't test for every instruction processed
			if  ( ! ( $instruction_count % 100 ) )
			   {
				// Global timeout handling
				if  ( $this -> Options  &  self::PDFOPT_ENFORCE_GLOBAL_EXECUTION_TIME )
				   {
					$now	=  microtime ( true ) ;

					if  ( $now - self::$GlobalExecutionStartTime  >  self::$MaxGlobalExecutionTime )
						error ( new PdfToTextTimeoutException ( "file {$this -> Filename}", true, self::$PhpMaxExecutionTime, self::$MaxGlobalExecutionTime ) ) ;
				    }

				// Per-instance timeout handling
				if  ( $this -> Options  &  self::PDFOPT_ENFORCE_EXECUTION_TIME )
				   {
					$now	=  microtime ( true ) ;

					if  ( $now - $this -> ExecutionStartTime  >  $this -> MaxExecutionTime )
						error ( new PdfToTextTimeoutException ( "file {$this -> Filename}", false, self::$PhpMaxExecutionTime, $this -> MaxExecutionTime ) ) ;
				    }
			    }

			// Character position after the current instruction
			$data_index 	=  $instruction [ 'next' ] ;

			// Process current instruction
			switch  ( $instruction [ 'instruction' ] )
			   {
				// Raw text (enclosed by parentheses) or array text (enclosed within square brackets)
				// is returned as a single instruction
			   	case 	'text' :
					// Empty arrays of text may be encountered - ignore them
					if  ( ! count ( $instruction [ 'values' ] ) )
						break ;

					// Check if we have to insert a newline
			   		if ( ! $use_same_line )
					   {
			   			$fragment 		.=  $this -> EOL ;
						$needs_separator	 =  false ;
					    }
			   		// Roughly simulate spacing between lines by inserting newline characters
			   		else if  ( $extra_newlines  > 0 )
			   		   {
			   			$fragment 		.=  str_repeat ( $this -> EOL, $extra_newlines ) ;
			   			$extra_newlines		 =  0 ;
						$needs_separator	 =  false ;
			   		    }
					else 
						$needs_separator	=  true ;

					// Add a separator if necessary
					if  ( $needs_separator )
					   {
						// If the Separator and BlockSeparator properties are the same (and not empty), only add a block separator if
						// the current result does not end with it
						if  ( $same_separators )
						   {
							if  ( $this -> Separator  !=  ''  &&  substr ( $fragment, - $separator_length )  !=  $this -> BlockSeparator )
								$fragment		.=  $this -> BlockSeparator ;
						    }
						else
							$fragment		.=  $this -> BlockSeparator ;
					    }

					$needs_separator	=  true ;
					$value_index		=  0 ;

					// Fonts having character maps will require some special processing
					if  ( $current_font_mapped )
					   {
					   	// Loop through each text value
			   			foreach  ( $instruction [ 'values' ]  as  $text )
			   			   {
			   		   		$is_hex 	=  ( $text [0]  ==  '<' ) ;
			   			   	$length 	=  strlen ( $text ) - 1 ;
							$handled	=  false ;

			   			   	// Characters are encoded within angle brackets ( "<>" ).
							// Note that several characters can be specified within the same angle brackets, so we have to take
							// into account the width we detected in the begincodespancerange construct 
			   			   	if  ( $is_hex )
			   			   	   {
			   			   	   	for  ( $i = 1 ; $i  <  $length ; $i += $current_font_map_width )
			   			   	   	   {
									$value		 =  substr ( $text, $i, $current_font_map_width ) ;
			   			   	   	   	$ch 		 =  hexdec ( $value ) ;

									if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ) )
										$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ;
									else if  ( $current_font  ==  -1 )
									   {
										$newchar	=  chr ( $ch ) ;
									    }
									else
									   {
										$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ch ) ;
										$this -> CharacterMapBuffer [ $current_font ] [ $ch ]		=  $newchar ;
									    }

			   			   			$fragment		.=  $newchar ;
			   			   	   	    }

								$handled	 =  true ;
			   			   	    }
							// Yes ! double-byte codes can also be specified as plain text within parentheses !
							// However, we have to be really careful here ; the sequence :
							//	(Be)
							// can mean the string "Be" or the Unicode character 0x4265 ('B' = 0x42, 'e' = 0x65)
							// We first look if the character map contains an entry for Unicode codepoint 0x4265 ;
							// if not, then we have to consider that it is regular text to be taken one character by
							// one character. In this case, we fall back to the "if ( ! $handled )" condition
							else if  ( $current_font_map_width  ==  4  )
							   {
								$temp_result		=  '' ;

								for  ( $i = 1 ; $i  <  $length ; $i ++ )
								   {
									// Each character in the pair may be a backslash, which escapes the next character so we must skip it
									// This code needs to be reviewed ; the same code is duplicated to handle escaped characters in octal notation
									if  ( $text [$i]  !=  '\\' )
										$ch1	=  $text [$i] ;
									else 
									   {
										$i ++ ;

										if  ( $text [$i]  <  '0'  ||  $text [$i]  >  '7' )
											$ch1	=  $this -> ProcessEscapedCharacter ( $text [$i] ) ;
										else
										   {
											$oct		=  '' ;
											$digit_count	=  0 ;

											while  ( $i  <  $length  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7'  &&  $digit_count  <  3 )
											   {
												$oct	.=  $text [$i ++] ;
												$digit_count ++ ;
											    }

											$ch1	=  chr ( octdec ( $oct ) ) ;
											$i -- ;
										    }
									    }

									$i ++ ;

									if  ( $text [$i]  != '\\' )
										$ch2	=  $text [$i] ;
									else
									   {
										$i ++ ;

										if  ( $text [$i]  <  '0'  ||  $text [$i]  >  '7' )
											$ch2	=  $this -> ProcessEscapedCharacter ( $text [$i] ) ;
										else
										   {
											$oct		=  '' ;
											$digit_count	=  0 ;

											while  ( $i  <  $length  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7'  &&  $digit_count  <  3 )
											   {
												$oct	.=  $text [$i ++] ;
												$digit_count ++ ;
											    }

											$ch2	=  chr ( octdec ( $oct ) ) ;
											$i -- ;
										    }
									    }

									// Build the 2-bytes character code
									$ch		=  ( ord ( $ch1 )  <<  8 )  |  ord ( $ch2 ) ;

									if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ) )
										$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ;
									else
									   {
										$newchar	=  $this -> FontTable -> MapCharacter ( $current_font, $ch, true ) ;
										$this -> CharacterMapBuffer [ $current_font ] [ $ch ]		=  $newchar ;
									    }

									// Yes !!! for characters encoded with two bytes, we can find the following construct :
									//	0x00 "\" "(" 0x00 "C" 0x00 "a" 0x00 "r" 0x00 "\" ")"
									// which must be expanded as : (Car)
									// We have here the escape sequences "\(" and "\)", but the backslash is encoded on two bytes
									// (although the MSB is nul), while the escaped character is encoded on 1 byte. waiting
									// for the next quirk to happen...
									if  ( $newchar  ==  '\\'  &&  isset ( $text [ $i + 2 ] ) )
									   {
										$newchar		=  $this -> ProcessEscapedCharacter ( $text [ $i + 2 ] ) ;
										$i ++ ;		// this time we processed 3 bytes, not 2
									    }

									$temp_result		.=  $newchar ;
								    }

								// Happens only if we were unable to translate a character using the current character map
								$fragment		.=  $temp_result ;
								$handled	 =  true ;
							    }

							// Character strings within parentheses.
							// For every text value, use the character map table for substitutions
							if  ( ! $handled )
							   {
				   		   		for  ( $i = 1 ; $i  <  $length ; $i ++ )
				   		   		   {
				   		   			$ch 		=  $text [$i] ;

									// Set to true to optimize calls to MapCharacters
									// Currently does not work with pobox@dizy.sk/infoma.pdf (a few characters differ)
									$use_map_buffer	=  false ;

									// ... but don't forget to handle escape sequences "\n" and "\r" for characters
									// 10 and 13
				   		   			if  ( $ch  ==  '\\' )
				   		   			   {
				   		   				$ch 	=  $text [++$i] ;

										// Escaped character
										if  ( $ch  <  '0'  ||  $ch  >  '7' )
											$ch		=  $this -> ProcessEscapedCharacter ( $ch ) ;
										// However, an octal form can also be specified ; in this case we have to take into account
										// the character width for the current font (if the character width is 4 hex digits, then we
										// will encounter constructs such as "\000\077").
										// The method used here is dirty : we build a regex to match octal character representations on a substring
										// of the text 
										else
										   {
											$width		=  $current_font_map_width / 2 ;	// Convert to byte count
											$subtext	=  substr ( $text, $i - 1 ) ;
											$regex		=  "#^ (\\\\ [0-7]{3}){1,$width} #imsx" ;

											$status		=  preg_match ( $regex, $subtext, $octal_matches ) ;

											if  ( $status )
											   {
												$octal_values	=  explode ( '\\', substr ( $octal_matches [0], 1 ) ) ;
												$ord		=  0 ;

												foreach  ( $octal_values  as  $octal_value ) 
													$ord	=  ( $ord  <<  8 ) + octdec ( $octal_value ) ;

												$ch	 =  chr ( $ord ) ;
												$i	+=  strlen ( $octal_matches [0] ) - 2 ;
											    }
										    }

										$use_map_buffer		=  false ;
				   		   			    }

									// Add substituted character to the output result
									$ord		 =  ord ( $ch ) ;

									if  ( ! $use_map_buffer )
										$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
									else
									   {
										if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ) )
											$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ;
										else
										   {
											$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
											$this -> CharacterMapBuffer [ $current_font ] [ $ord ]	=  $newchar ;
										    }
									    }

									$fragment		.=  $newchar ;
				   		   		    }
							    }

							// Handle offsets between blocks of characters
							if  ( isset ( $instruction [ 'offsets' ] [ $value_index ] )  &&
									- ( $instruction [ 'offsets' ] [ $value_index ] )  >  $this -> MinSpaceWidth )
								$fragment		.=  $this -> __get_character_padding ( $instruction [ 'offsets' ] [ $value_index ] ) ;

							$value_index ++ ;
			   		   	    }
			   		    }
					// For fonts having no associated character map, we simply encode the string in UTF8
					// after the C-like escape sequences have been processed
					// Note that <xxxx> constructs can be encountered here, so we have to process them as well
			   		else
			   		   {
			   			foreach  ( $instruction [ 'values' ]  as  $text )
			   			   {
			   			   	$is_hex 	=  ( $text [0]  ==  '<' ) ;
			   			   	$length 	=  strlen ( $text ) - 1 ;

							// Some text within parentheses may have a backslash followed by a newline, to indicate some continuation line.
							// Example :
							//	(this is a sentence \
							//	 continued on the next line)
							// Funny isn't it ? so remove such constructs because we don't care
							$text		=  str_replace ( array ( "\\\r\n", "\\\r", "\\\n" ), '', $text ) ;

			   			   	// Characters are encoded within angle brackets ( "<>" )
			   			   	if  ( $is_hex )
			   			   	   {
			   			   	   	for  ( $i = 1 ; $i  <  $length ; $i += 2 )
			   			   	   	   {
			   			   	   	   	$ch 	=  hexdec ( substr ( $text, $i, 2 ) ) ;

			   			   			$fragment .=  $this -> CodePointToUtf8 ( $ch ) ;
			   			   	   	    }
			   			   	    }
							// Characters are plain text
			   			   	else
							   {
								$text	=  self::Unescape ( $text ) ;

								for  ( $i = 1, $length = strlen ( $text ) - 1 ; $i  <  $length ; $i ++ )
								   {
									$ch	=  $text [$i] ;
									$ord	=  ord ( $ch ) ;

									if  ( $ord  <  127 )
										$newchar	=  $ch ;
									else
									   {
										if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ) )
											$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ;
										else
										   {
											$newchar	=  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
											$this -> CharacterMapBuffer [ $current_font ] [ $ord ]	=  $newchar ;
										    }
									    }

									$fragment		.=  $newchar ;
								    }
							    }

							// Handle offsets between blocks of characters
							if  ( isset ( $instruction [ 'offsets' ] [ $value_index ] )  &&
									abs ( $instruction [ 'offsets' ] [ $value_index ] )  >  $this -> MinSpaceWidth )
								$fragment		.=  $this -> __get_character_padding ( $instruction [ 'offsets' ] [ $value_index ] ) ;

							$value_index ++ ;
			   			   }
			   		    }

					// Process the markers which do not have an associated font yet - this will be done by matching
					// the current text fragment against one of the regular expressions defined.
					// If a match occurs, then all the subsequent text fragment using the same font will be put markers
					for  ( $j = 0 ; $j  <  $unprocessed_marker_count ; $j ++ )
					   {
						$marker			=  $this -> UnprocessedMarkerList [ 'font' ] [$j] ;

						if  ( preg_match ( $marker [ 'regex' ], trim ( $fragment ) ) )
						   {
							$this -> TextWithFontMarkers [ $current_font ]	=  array
							   (
								'font'		=>  $current_font,
								'height'	=>  $current_font_size,
								'regex'		=>  $marker   [ 'regex' ],
								'start'		=>  $marker   [ 'start' ],
								'end'		=>  $marker   [ 'end'   ] 
							    ) ;

							$unprocessed_marker_count -- ;
							unset ( $this -> UnprocessedMarkerList [ 'font' ] [$j] ) ;

							break ;
						    }
					    }

					// Check if we need to add markers around this text fragment
					if  ( isset ( $this -> TextWithFontMarkers [ $current_font ] )  &&  
							$this -> TextWithFontMarkers [ $current_font ] [ 'height' ]  ==  $current_font_size )
					    {
						$fragment	=  $this -> TextWithFontMarkers [ $current_font ] [ 'start' ] .
								   $fragment .
								   $this -> TextWithFontMarkers [ $current_font ] [ 'end' ] ;
					     }

					$result		.=  $fragment ;

					break ;

				// An "nl" instruction means TJ, Tj, T* or "'"
			   	case 	'nl' :
			   		if  ( ! $instruction [ 'conditional' ] )
			   		   {
			   		   	if  ( $instruction [ 'leading' ]  &&  $text_leading  &&  $current_font_size )
			   		   	   {
			   		   		$count 	=  ( integer ) ( ( $text_leading - $current_font_size ) / $current_font_size ) ;

			   		   		if  ( ! $count )
			   		   			$count 	=  1 ;
			   		   	    }
			   		   	else
			   		   		$count 	=  1 ;

		   		   		$extra			 =  str_repeat ( PHP_EOL, $count ) ;
			   			$result 		.=  $extra ;
						$needs_separator	 =  false ;
						$last_goto_y 		-=  ( $count * $text_leading ) ;	// Approximation on y-coord change
						$last_relative_goto_y	 =  0 ;
			   		    }

			   		break ;

				// "Tm", "Td" or "TD" : Output text on the same line, if the "y" coordinates are equal
			   	case 	'goto' :
					// Some text is positioned using 'Tm' instructions ; however they can be immediatley followed by 'Td' instructions
					// which give a relative positioning ; so consider that the last instruction wins
					if  ( $instruction [ 'relative' ] )
					   {
						// Try to put a separator if the x coordinate is non-zero
						//if  ( $instruction [ 'x' ] - $last_goto_x  >=  $current_font_size )
						//	$result		.=  $this -> Separator ;

						$discard_last_instruction	=  true ;
						$extra_newlines			=  0 ; 
						$use_same_line			=  ( ( $last_relative_goto_y - abs ( $instruction  [ 'y' ] ) )  <=  $current_font_size ) ;
						$last_relative_goto_y		=  abs ( $instruction [ 'y' ] ) ;
						$last_goto_x			=  $instruction [ 'x' ] ;
						
						if  ( - $instruction [ 'y' ]  >  $current_font_size ) 
						   {
							$use_same_line		=  false ;

							if  ( $last_relative_goto_y )
								$extra_newlines		=  ( integer ) ( $current_font_size / $last_relative_goto_y ) ;
							else
								$extra_newlines		=  0 ;
						    }
						else if  ( ! $instruction [ 'y' ] ) 
						   {
							$use_same_line		=  true ;
							$extra_newlines		=  0 ;
						    }
							
						break ;
					    }
					else
						$last_relative_goto_y	=  0 ;

					$y	=  $last_goto_y + $last_relative_goto_y ;

			   		if  ( $instruction [ 'y' ]  ==  $y  ||  abs ( $instruction [ 'y' ] - $y )  <  $current_font_size )
			   		   {
			   			$use_same_line 		=  true ;
			   			$extra_newlines 	=  0 ;
			   		    }
					else
					   {
					   	// Compute the number of newlines we have to insert between the current and the next lines
					   	if  ( $current_font_size )
					   		$extra_newlines =  ( integer ) ( ( $y - $instruction [ 'y' ] - $current_font_size ) / $current_font_size ) ;

						$use_same_line 		=  ( $last_goto_y  ==  0 ) ;
					    }

					$last_goto_y 		=  $instruction [ 'y' ] ;
			   		break ;

				// Set font size
			   	case 	'fontsize' :
			   		$current_font_size 	=  $instruction [ 'size' ] ;
			   		break ;

				// "/Rx" : sets the current font
			   	case 	'resource' :
			   		$current_font 		=  $instruction [ 'resource' ] ;

					$this -> FontTable -> GetFontAttributes ( $page_number, $current_template, $current_font, $current_font_map_width, $current_font_mapped ) ;
			   		break ;

				// "/TPLx" : references a template, which can contain additional font aliases
				case	'template' :
					if  ( $this -> PageMap -> IsValidXObjectName ( $instruction [ 'token' ] ) )
						$current_template	=  $instruction [ 'token' ] ;

					break ;

			   	// 'TL' : text leading to be used for the next "T*" in the flow
			   	case	'leading' :
					if  ( ! ( $this -> Options & self::PDFOPT_IGNORE_TEXT_LEADING ) )
			   			$text_leading 		=  $instruction [ 'size' ] ;

			   		break ;


				// 'ET' : we have to reset a few things here
				case	'ET' :
					$current_font			=  -1 ;
					$current_font_map_width		=  2 ;
					break ;
			    }

			// Remember last instruction - this will help us into determining whether we should put the next text
			// on the current or following line
			if  ( ! $discard_last_instruction )
				$last_instruction 	=  $instruction ;

			$discard_last_instruction	=  false ;
		    }

		return ( $this -> __rtl_process ( $result ) ) ;
	    }



	// __next_instruction -
	//	Retrieves the next instruction from the drawing text block.
	private function  __next_instruction ( $page_number, $data, $data_length, $index, $current_template )
	   {
		static 	$last_instruction 	=  false ;

		$ch	=  '' ;

		// Constructs such as
		if  ( $last_instruction )
		   {
			$result 		=  $last_instruction ;
			$last_instruction	=  false ;

			return ( $result ) ;
		    }

		// Whether we should compute enhanced statistics
		$enhanced_statistics		=  $this -> EnhancedStatistics ;

		// Holds the floating-point values encountered so far
		$number_stack 	=  array ( ) ;

		// Loop through the stream of tokens
		while  ( ( $part = $this -> __next_token ( $page_number, $data, $data_length, $index ) )  !==  false )
		   {
			$token 		=  $part [0] ;
			$next_index 	=  $part [1] ;

			// Floating-point number : push it onto the stack
			if  ( ( $token [0]  >=  '0'  &&  $token [0]  <=  '9' )  ||  $token [0]  ==  '-'  ||  $token [0]  ==  '+'  ||  $token [0]  ==  '.' )
			   {
				$number_stack []	=  $token ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'operand' ] ++ ;
			    }
			// 'Tm' instruction : return a "goto" instruction with the x and y coordinates
			else if  ( $token  ==  'Tm' )
			   {
				$x 	=  $number_stack [4] ;
				$y 	=  $number_stack [5] ;

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Tm' ] ++ ;

				return ( array ( 'instruction' => 'goto', 'next' => $next_index, 'x' => $x, 'y' => $y, 'relative' => false, 'token' => $token ) ) ;
			    }
			// 'Td' or 'TD' instructions : return a goto instruction with the x and y coordinates (1st and 2nd args)
			else if  ( $token  ==  'Td'  ||  $token  ==  'TD' )
			   {
				$x 		=  $number_stack [0] ;
				$y 		=  $number_stack [1] ;

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ $token ] ++ ;

				return ( array ( 'instruction' => 'goto', 'next' => $next_index, 'x' => $x, 'y' => $y, 'relative' => true, 'token' => $token ) ) ;
			    }
			// Output text "'" instruction, with conditional newline
			else if  ( $token [0]  ==  "'" )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ "'" ] ++ ;

				return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => true, 'leading' => false, 'token' => $token ) ) ;
			    }
			// Same as above
			else if  ( $token  ==  'TJ'  ||  $token  ==  'Tj' )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ $token ] ++ ;

				return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => true, 'leading' => false, 'token' => $token ) ) ;
			    }
			// Set font size
			else if  ( $token  ==  'Tf' )
			    {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Tf' ] ++ ;

				return ( array ( 'instruction' => 'fontsize', 'next' => $next_index, 'size' => $number_stack [0], 'token' => $token ) ) ;
			     }
			// Text leading (spacing used by T*)
			else if  ( $token  ==  'TL' )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'TL' ] ++ ;

				return ( array ( 'instruction' => 'leading', 'next' => $next_index, 'size' => $number_stack [0], 'token' => $token ) ) ;
			    }
			// Position to next line
			else if  ( $token  ==  'T*' )
			    {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'T*' ] ++ ;
				
				return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => true, 'leading' => true ) ) ;
			     }
			// Draw object ("Do"). To prevent different text shapes to appear on the same line, we return a "newline" instruction
			// here. Note that the shape position is not taken into account here, and shapes will be processed in the order they
			// appear in the pdf file (which is likely to be different from their position on a graphic screen).
			else if  ( $token  ==  'Do' )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'ignored' ] ++ ;

				return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => false, 'leading' => false, 'token' => $token ) ) ;
			    }
			// Raw text output
			else if  ( $token [0]  ==  '(' )
			   {
			   	$next_part 	=  $this -> __next_token ( $page_number, $data, $data_length, $next_index, $enhanced_statistics ) ;
			   	$instruction	=  array ( 'instruction' => 'text', 'next' => $next_index, 'values' => array ( $token ), 'token' => $token ) ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '(' ] ++ ;

			   	if  ( $next_part [0]  ==  "'" )
			   	   {
			   	   	$last_instruction  	=  $instruction ;
			   	   	return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => false, 'leading' => true, 'token' => $token ) ) ;
			   	   }
			   	else
					return ( $instruction ) ;
			    }
			// Hex digits within angle brackets
		   	else if  ( $token [0]  ==  '<'  )
			   {
				$ch		=  $token [1] ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '<' ] ++ ;
			   	$instruction	=  array ( 'instruction' => 'text', 'next' => $next_index, 'values' => array ( $token ), 'token' => $token ) ;

				if  ( self::$CharacterClasses [ $ch ] & self::CTYPE_ALNUM )
				   {
			   		$next_part 	=  $this -> __next_token ( $page_number, $data, $data_length, $next_index ) ;
			   		$instruction	=  array ( 'instruction' => 'text', 'next' => $next_index, 'values' => array ( $token ), 'token' => $token ) ;

			   		if  ( $next_part [0]  ==  "'" )
			   		   {
			   	   		$last_instruction  	=  $instruction ;
			   	   		return ( array ( 'instruction' => 'nl', 'next' => $next_index, 'conditional' => false, 'leading' => true, 'token' => $token ) ) ;
			   		   }
			   		else
						return ( $instruction ) ;
				    }
			    }
			    // Text specified as an array of individual raw text elements, and individual interspaces between characters
			else if  ( $token [0]  ==  '[' )
			   {
				$values 	=  $this -> __extract_chars_from_array ( $token ) ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '[' ] ++ ;
				$instruction 	=  array ( 'instruction' => 'text', 'next' => $next_index, 'values' => $values [0], 'offsets' => $values [1], 'token' => $token ) ;

				return ( $instruction ) ;
			    }
			// Token starts with a slash : maybe a font specification
			else if  ( preg_match ( '#^ ( ' . self::$FontSpecifiers . ' ) #ix', $token ) )
			   {
				$key	=  "$page_number:$current_template:$token" ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'operand' ] ++ ;

				if  ( isset ( $this -> MapIdBuffer [ $key ] ) )
					$id	=   $this -> MapIdBuffer [ $key ] ;
				else
				   {
					$id 	=  $this -> FontTable -> GetFontByMapId ( $page_number, $current_template, $token ) ;

					$this -> MapIdBuffer [ $key ]	=  $id ;
				    }

				return ( array ( 'instruction' => 'resource', 'next' => $next_index, 'resource' => $id, 'token' => $token ) ) ;
			    }
			// Template reference, such as /TPL1. Each reference has initially been replaced by !PDFTOTEXT_TEMPLATE_TPLx during substitution
			// by ProcessTemplateReferences(), because templates not only specify text to be replaced, but also font aliases
			// -and this is the place where we catch font aliases in this case
			else if  ( preg_match ( '/ !PDFTOTEXT_TEMPLATE_ (?P<template> \w+) /ix', $token, $match ) )
			   {
				$current_template	=  '/' . $match [ 'template' ] ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'template' ] ++ ;

				return ( array ( 'instruction' => 'template', 'next' => $next_index, 'token' => $current_template ) ) ;
			    }
			// Others, only counted for statistics
			else if  ( $token  ===  'cm' )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'cm' ] ++ ;
			    }
			else if  ( $token  ===  'BT' )
			   {
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'BT' ] ++ ;

				return ( array ( 'instruction' => 'BT', 'next' => $next_index, 'token' => $token ) ) ;
			    }
			else if  ( $token  ==  'ET' )	// Nothing special to count here
			   {
				return ( array ( 'instruction' => 'ET', 'next' => $next_index, 'token' => $token ) ) ;
			    }
			// Other instructions : we're not that much interested in them, so clear the number stack and consider
			// that the current parameters, floating-point values, have been processed
			else
			   {
				$number_stack 	=  array ( ) ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'ignored' ] ++ ;
			    }

			$index 		=  $next_index ;
		    }

		// End of input
		return ( false ) ;
	    }


	// __next_token :
	//	Retrieves the next token from the drawing instructions stream.
	private function  __next_token ( $page_number, $data, $data_length, $index )
	   {
		// Skip spaces
		$count		=  0 ;

		while  ( $index  <  $data_length  &&  ( $data [ $index ]  ==  ' '  ||  $data [ $index ]  ==  "\t"  ||  $data [ $index ]  ==  "\r"  ||  $data [ $index ]  ==  "\n" ) )
		   {
			$index ++ ; 
			$count ++ ;
		    }

		$enhanced_statistics	=  $this -> EnhancedStatistics ;
		$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'space' ]	+=  $count ;

		// End of input
		if  ( $index  >=  $data_length )
			return ( false ) ;

		// The current character will tell us what to do
		$ch 	=  $data [ $index ] ;
		$ch2	=  '' ;

		switch ( $ch )
		   {
			// Opening square bracket : we have to find the closing one, taking care of escape sequences
			// that can also specify a square bracket, such as "\]"
		   	case 	"[" :
		   		$pos 		=  $index + 1 ;
		   		$parent 	=  0 ;
		   		$angle 		=  0 ;
		   		$result		=  $ch ;

		   		while  ( $pos  <  $data_length )
		   		   {
		   			$nch 	=  $data [ $pos ++ ] ;

		   			switch  ( $nch )
		   			   {
		   			   	case 	'(' :
		   			   		$parent ++ ;
		   			   		$result 	.=  $nch ;
		   			   		break ;

		   			   	case 	')' :
		   			   		$parent -- ;
		   			   		$result 	.=  $nch ;
		   			   		break ;

		   			   	case 	'<' :
							// Although the array notation can contain hex digits between angle brackets, we have to
							// take care that we do not have an angle bracket between two parentheses such as :
							// [ (<) ... ]
							if  ( ! $parent )
		   			   			$angle ++ ;

		   			   		$result 	.=  $nch ;
		   			   		break ;

		   			   	case 	'>' :
							if  ( ! $parent )
		   			   			$angle -- ;

		   			   		$result 	.=  $nch ;
		   			   		break ;

		   			   	case 	'\\' :
		   					$result 	.=  $nch . $data [ $pos ++ ] ;
		   					break ;

		   			   	case 	']' :
		   					$result 	.=  ']' ;

		   					if  ( ! $parent  )
		   						break  2 ;
		   					else
		   						break ;

						case	"\n" :
						case	"\r" :
							break ;

		   			   	default :
		   			   		$result 	.=  $nch ;
		   			    }
		   		    }

		   		return ( array ( $result, $pos ) ) ;

			// Parenthesis : Again, we have to find the closing parenthesis, taking care of escape sequences
			// such as "\)"
		   	case 	"(" :
		   		$pos 		=  $index + 1 ;
		   		$result		=  $ch ;

		   		while  ( $pos  <  $data_length )
		   		   {
		   			$nch 	=  $data [ $pos ++ ] ;

		   			if  ( $nch  ==  '\\' )
					   {
						$after		 =  $data [ $pos ] ;

						// Character references specified as \xyz, where "xyz" are octal digits
						if  ( $after  >=  '0'  &&  $after  <=  '7' )
						   {
							$result		.=  $nch ;

							while  ( $data [ $pos ]  >=  '0'  &&  $data [ $pos ]  <=  '7' )
								$result		.=  $data [ $pos ++ ] ;
						    }
						// Regular character escapes
						else
		   					$result 	.=  $nch . $data [ $pos ++ ] ;
					    }
		   			else if  ( $nch  ==  ')' )
		   			   {
		   				$result 	.=  ')' ;
		   				break ;
		   			    }
		   			else
		   				$result 	.=  $nch ;
		   		   }

		   		return ( array ( $result, $pos ) ) ;

			// A construction of the form : "<< something >>", or a unicode character
		   	case 	'<' :
				if  ( ! isset ( $data [ $index + 1 ] ) )
					return ( false ) ;

		   		if (  $data [ $index + 1 ]  ==  '<' )
		   		   {
		   		   	$pos 	=  strpos ( $data, '>>', $index + 2 ) ;

		   			if  ( $pos  ===  false )
		   				return ( false ) ;

		   			return ( array ( substr ( $data, $index, $pos - $index + 2 ), $pos + 2 ) ) ;
		   		    }
		   		else
		   		   {
		   		   	$pos 	=  strpos ( $data, '>', $index + 2 ) ;

		   			if  ( $pos  ===  false )
		   				return ( false ) ;

					// There can be spaces and newlines inside a series of hex digits, so remove them...
					$result			=  preg_replace ( '/\s+/', '', substr ( $data, $index, $pos - $index + 1 ) ) ;

		   			return ( array ( $result, $pos + 1 ) ) ;
		   		   }

			// Tick character : consider it as a keyword, in the same way as the "TJ" or "Tj" keywords
		   	case 	"'" :
		   		return ( array ( "'", $index + 1 ) ) ;

			// Other cases : this may be either a floating-point number or a keyword
		   	default :
		   		$index ++ ;
		   		$value 	=  $ch ;

				if  ( isset ( $data [ $index ] ) )
				   {
		   			if ( ( self::$CharacterClasses [ $ch ] & self::CTYPE_DIGIT )  ||  
							$ch  ==  '-'  ||  $ch  ==  '+'  ||  $ch  ==  '.' )
		   			   {
		   				while  ( $index  <  $data_length  &&
		   						( ( self::$CharacterClasses [ $data [ $index ] ] & self::CTYPE_DIGIT )  ||  
									$data [ $index ]  ==  '.' ) )
		   					$value 	.=  $data [ $index ++ ] ;
		   			    }
		   			else if  ( ( self::$CharacterClasses [ $ch ] & self::CTYPE_ALPHA )  ||  
							$ch  ==  '/'  ||  $ch  ==  '!' )
		   			   {
						$ch	=  $data [ $index ] ;

						while  ( $index  <  $data_length  &&  
							( ( self::$CharacterClasses [ $ch ] & self::CTYPE_ALNUM )  ||  
								$ch  ==  '*'  ||  $ch  ==  '-'  ||  $ch  ==  '_'  ||  $ch  ==  '.'  ||  $ch  ==  '+' ) )
						   {
							$value 	.=  $ch ;
							$index ++ ;

							if  ( isset ( $data [ $index ] ) )
								$ch	=  $data [ $index ] ;
						    }
		   			    }
				    }

		   		return ( array ( $value, $index ) ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        ExtractTextWithLayout - Extracts text, trying to render the page layout.
	
		$text 	=  $this -> ExtractTextWithLayout ( $page_number, $object_id, $data, &$current_font ) ;

	    DESCRIPTION
	        Extracts text from decoded stream contents, trying to render the layout.

	    PARAMETERS
		$page_number (integer) -
			¨Page number that contains the text to be extracted.

	    	$object_id (integer) -
	    		Object id of this text block.

	    	$data (string) -
	    		Stream contents.

		$current_font (integer) -
			Id of the current font, which should be found in the $this->FontTable property, if anything
			went ok.
			This parameter is required, since text blocks may not specify a new font resource id and reuse
			the one that waas set before.

	    RETURN VALUE
		Returns the decoded text.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  ExtractTextWithLayout ( &$page_fragments, $page_number, $object_id, $data, &$current_font )
	   {
		// Characters that can start a numeric operand
		static		$numeric_starts		=  array
		   (
			'+' => true, '-' => true, '.' => true, '0' => true, '1' => true, '2' => true, '3' => true, '4' => true,
			'5' => true, '6' => true, '7' => true, '8' => true, '9' => true
		    ) ;
		// Initial (default) transformation matrix. To reflect the PDF specifications, we will keep it as a 6 elements array :
		//	[ sx tx ty sy x y ]
		// (although tx and ty are not useful here, since they affect the graphic orientation of the text)
		// sx and sy are scaling parameters, actually a multiplier for the x and y parameters. We only keep 
		static		$IdentityMatrix		=  array ( 1, 0, 0, 1, 0, 0 ) ;

		// Remove useless instructions
		$new_data	=  $this -> __strip_useless_instructions ( $data ) ;

		if  ( self::$DEBUG )
		   {
			echo "\n----------------------------------- TEXT #$object_id (size = " . strlen ( $data ) . " bytes, new size = " . strlen ( $new_data ) . " bytes)\n" ;
			echo $data ;
			echo "\n----------------------------------- OPTIMIZED TEXT #$object_id (size = " . strlen ( $data ) . " bytes, new size = " . strlen ( $new_data ) . " bytes)\n" ;
			echo $new_data ;
		    }

		$data					=  $new_data ;
		$data_length 				=  strlen ( $data ) ;		// Data length

		$page_fragment_count			=  count ( $page_fragments ) ;

		// Index into the specified block of text-drawing instructions
		$data_index 				=  0 ;

		// Text matrices
		$CTM					=  
		$Tm					=  $IdentityMatrix ;

		// Nesting level of BT..ET instructions (Begin text/End text) - they are not nestable but be prepared to meet buggy PDFs
		$BT_nesting_level			=  0 ;

		// Current font data
		$current_font_height			=  0 ;

		// Current font map width, in bytes, plus a flag saying whether the current font is mapped or not
		$current_template	=  '' ;
		$current_font_name	=  '' ;
		$this -> FontTable -> GetFontAttributes ( $page_number, $current_template, $current_font, $current_font_map_width, $current_font_mapped ) ;

		// Operand stack
		$operand_stack				=  array ( ) ;

		// Number of tokens processed so far
		$token_count				=  0 ;

		// Page attributes
		$page_attributes			=  $this -> PageMap -> PageAttributes [ $page_number ] ;

		// Graphics context stack - well, we only store here the current transformation matrix
		$graphic_stack				=  array ( ) ;
		$graphic_stack_size			=  0 ;

		// Global/local execution time measurements
		$tokens_between_timechecks	=  1000 ;
		$enforce_global_execution_time	=  $this -> Options  &  self::PDFOPT_ENFORCE_GLOBAL_EXECUTION_TIME ;
		$enforce_local_execution_time	=  $this -> Options  &  self::PDFOPT_ENFORCE_EXECUTION_TIME ;
		$enforce_execution_time		=  $enforce_global_execution_time | $enforce_local_execution_time ;

		// Whether we should compute enhanced statistics
		$enhanced_statistics		=  $this -> EnhancedStatistics ;

		// Whether we should show debug coordinates
		$show_debug_coordinates		=  ( $this -> Options & self::PDFOPT_DEBUG_SHOW_COORDINATES ) ;

		// Text leading value set by the TL instruction
		$text_leading			=  0.0 ;

		// Loop through the stream of tokens
		while  ( $this -> __next_token_ex ( $page_number, $data, $data_length, $data_index, $token, $next_index )  !==  false )
		   {
			$token_start	=  $token [0] ;
			$token_count ++ ;
			$length		=  $next_index - $data_index - 1 ;

			// Check if we need to enforce execution time checking, to prevent PHP from terminating our script without any hope
			// of catching the error
			if  ( $enforce_execution_time  &&  ! ( $token_count % $tokens_between_timechecks ) )
			   {
				if  ( $enforce_global_execution_time )
				   {
					$now	=  microtime ( true ) ;

					if  ( $now - self::$GlobalExecutionStartTime  >  self::$MaxGlobalExecutionTime )
						error ( new PdfToTextTimeoutException ( "file {$this -> Filename}", true, self::$PhpMaxExecutionTime, self::$MaxGlobalExecutionTime ) ) ;
				    }

				// Per-instance timeout handling
				if  ( $enforce_local_execution_time )
				   {
					$now	=  microtime ( true ) ;

					if  ( $now - $this -> ExecutionStartTime  >  $this -> MaxExecutionTime )
						error ( new PdfToTextTimeoutException ( "file {$this -> Filename}", false, self::$PhpMaxExecutionTime, $this -> MaxExecutionTime ) ) ;
				    }
			    }

			/****************************************************************************************************************
			
				The order of the testings is important for maximum performance : put the most common cases first.
				A study on over 1000 PDF files has shown the following :

				- Instruction operands appear 24.5 million times
				- Tx instructions (including Tf, Tm, ', ", etc.) : 24M
				- (), <> and [] constructs for drawing text : 17M
				- Other : peanuts...
				- Ignored instructions : 0.5M (these are the instructions without interest for text extraction and that
				 could not be removed by the __strip_useless_instructions() method).

				Of course, white spaces appear more than 100M times between instructions. However, it gets hard to remove
				most of them without compromising the result of __strip_useless_instructions.
			
			 ***************************************************************************************************************/
			// Numeric or flag for an instruction
			if  ( $token_start  ==  '/'  ||  isset ( $numeric_starts [ $token_start ] ) )
			   {
				$operand_stack []		=  $token ;

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'operand' ] ++ ;
			    }
			// A 2-characters "Tx" or a 1-character quote/doublequote instruction
			else if  ( ( $length  ===  2   &&  $token_start  ===  'T' )  ||  ( $length  ===  1  &&  ( $token_start  ===  "'"  ||  $token_start  ===  '"' ) ) )
			   {
				switch  ( ( $length  ===  1 ) ?  $token [0] : $token [1] ) 
				   {
					// Tj instruction
					case	'j' :
						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Tj' ] ++ ;
						break ;

					// Tm instruction
					case	'm' :
						$Tm [0]			=  ( double ) $operand_stack [0] ;
						$Tm [1]			=  ( double ) $operand_stack [1] ;
						$Tm [2]			=  ( double ) $operand_stack [2] ;
						$Tm [3]			=  ( double ) $operand_stack [3] ;
						$Tm [4]			=  ( double ) $operand_stack [4] ;
						$Tm [5]			=  ( double ) $operand_stack [5] ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Tm' ] ++ ;
						break ;

					// Tf instruction
					case	'f' :
						$current_font_name	=  $operand_stack [0] ;
						$key			=  "$page_number:$current_template:$current_font_name" ;

						// We have to map a font specifier (such /TT0, C0-1, etc.) into an object id.
						// Check first if we already met this font
						if  ( isset ( $this -> MapIdBuffer [ $key ] ) )
							$current_font	=   $this -> MapIdBuffer [ $key ] ;
						// Otherwise retrieve its corresponding object number and put it in our font cache
						else
						   {
							$current_font 	=  $this -> FontTable -> GetFontByMapId ( $page_number, $current_template, $current_font_name ) ;

							$this -> MapIdBuffer [ $key ]	=  $current_font ;
						    }

						$current_font_height	=  ( double ) $operand_stack [1] ;
						$this -> FontTable -> GetFontAttributes ( $page_number, $current_template, $current_font, $current_font_map_width, $current_font_mapped ) ;
						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Tf' ] ++ ;
						break ;

					// Td instruction
					case	'd' :
						$Tm [4]		+=  ( double ) $operand_stack [0] * abs ( $Tm [0] ) ;
						$Tm [5]		+=  ( double ) $operand_stack [1] * abs ( $Tm [3] ) ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'Td' ] ++ ;
						break ;

					// TJ instruction
					case	'J' :
						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'TJ' ] ++ ;
						break ;

					// TD instruction
					case	'D' :
						$Tm [4]		 +=  ( double ) $operand_stack [0] * $Tm [0] ;
						$Tm [5]		 +=  ( double ) $operand_stack [1] * $Tm [3] ;
						$text_leading	 -=  $Tm [5] ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'TD' ] ++ ;
						break ;

					// T* instruction
					case	'*' :
						$Tm [4]		 =  0.0 ;
						$Tm [5]		-=  $text_leading ; //$current_font_height ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'T*' ] ++ ;
						break ;

					// TL instruction - Set text leading. Currently not used.
					case	'L' :
						$text_leading		=  ( double ) $operand_stack [0] ;
						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'TL' ] ++ ;
						break ;

					// ' instruction : go to next line and display text
					case	"'" :
						// Update the coordinates of the last text block found so far 
						$page_fragments [ $page_fragment_count - 1 ] [ 'x' ]		+=  $text_leading ;
						$offset								 =  $current_font_height * abs ( $Tm [3] ) ;
						$page_fragments [ $page_fragment_count - 1 ] [ 'y' ]		-=  $offset ;

						// And don't forget to update the y coordinate of the current transformation matrix
						$Tm [5]								-=  $offset ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ "'" ] ++ ;
						break ;

					// "'" instruction
					case	'"' :
						if  ( self::$DEBUG )
							warning ( "Instruction $token not yet implemented." ) ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '"' ] ++ ;
						break ;

					// Other : ignore them
					default :
						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'ignored' ] ++ ;
				    }

				$operand_stack		=  array ( ) ;
			    }
			// cm instruction
			else if  ( $token  ==  'cm' )
			   {
				$a		=  ( double ) $operand_stack [0] ;
				$b		=  ( double ) $operand_stack [1] ;
				$c		=  ( double ) $operand_stack [2] ;
				$d		=  ( double ) $operand_stack [3] ;
				$e		=  ( double ) $operand_stack [4] ;
				$f		=  ( double ) $operand_stack [5] ;

				$CTM		=  array ( $a, $b, $c, $d, $e, $f ) ;
				$operand_stack	=  array ( ) ;

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'cm' ] ++ ;
			    }
			// q/Q instructions (save/restore graphic context)
			else if  ( $token  ===  'q'  )
			   {
				$graphic_stack [ $graphic_stack_size ++ ]	=  array ( $CTM, $Tm ) ;
				$operand_stack					=  array ( ) ;
			    }
			else if  ( $token  ===  'Q' )
			    {
				if  ( $graphic_stack_size )
					list ( $CTM, $Tm )				=  $graphic_stack [ -- $graphic_stack_size ] ;
				else if  ( self::$DEBUG )
					warning ( "Tried to restore graphics context from an empty stack." ) ;

				$operand_stack					=  array ( ) ;
			    }
			// Text array in the [...] notation. Well, in fact, even non-array constructs are returned as an array by the
			// __next_token() function, for the sake of simplicity
			else if  ( $token_start  ===  '[' )
			   {
				$text			=  $this -> __decode_text ( $token, $current_font, $current_font_mapped, $current_font_map_width ) ;

				if  ( $text  !==  '' )
				   {
					$r		=  $this -> __matrix_multiply ( $Tm, $CTM, $page_attributes [ 'width' ], $page_attributes [ 'height' ] ) ;
					$fragment	=  array
					   (
						'x'		=>  ( $r [4]  <  0 ) ?  0.0 : $r [4],
						'y'		=>  ( $r [5]  <  0 ) ?  0.0 : $r [5],
						'page'		=>  $page_number,
						'template'	=>  $current_template,
						'font'		=>  $current_font_name,
						'font-height'	=>  abs ( $current_font_height * $Tm [3] ),
						'text'		=>  $text,
					    ) ;

					// Add debug information when needed
					if  ( self::$DEBUG )
					   {
						$fragment	=  array_merge
						   (
							$fragment, 
							array 
							   (
								'CTM'			=>  $CTM, 
								'Tm'			=>  $Tm, 
								'New Tm'		=>  $r, 
								'Real font height'	=>  $current_font_height, 
								'Page width'		=>  $page_attributes [ 'width' ], 
								'Page height'		=>  $page_attributes ['height' ]
							    )
						    ) ;
					    }

					// Add this text fragment to the list
					$page_fragments []	=  $fragment ;
					$page_fragment_count ++ ;

					$operand_stack		=  array ( ) ;
				    }
			    }
			// BT instruction
			else if  ( $token  ==  'BT' )
			   {
				$BT_nesting_level ++ ;
				$operand_stack					=  array ( ) ;
				$graphic_stack [ $graphic_stack_size ++ ]	=  array ( $CTM, $Tm ) ;

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'BT' ] ++ ;
			    }
			// ET instruction 
			else if  ( $token  ==  'ET' )
			   {
				if  ( $BT_nesting_level )
				   {
					$BT_nesting_level -- ;

					if  ( ! $BT_nesting_level  &&  $graphic_stack_size )
					   {
						list ( $CTM, $Tm )	=  $graphic_stack [ -- $graphic_stack_size ] ;
					    }

				    }

				$operand_stack		=  array ( ) ;
			    }
			// Template (substituted in __next_token)
			else if  ( $token_start  ===  '!' )
			   {
				if  ( preg_match ( '/ !PDFTOTEXT_TEMPLATE_ (?P<template> \w+) /ix', $token, $match ) )
				   {
					$name	=  '/' . $match [ 'template' ] ;
					$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'template' ] ++ ;

					if  ( $this -> PageMap -> IsValidXObjectName ( $name ) )
						$current_template	=  $name ;
				    }
				else
					$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'ignored' ] ++ ;

				$operand_stack	=  array ( ) ;
			    }
			// Other instructions
			else
			   {
				$operand_stack		=  array ( ) ;
				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'ignored' ] ++ ;
			    }

			// Update current index in instruction stream
			$data_index	=  $next_index ;
		    }
	    }


	// __matrix_multiply -
	//	Multiplies matrix $ma by $mb.
	//	PDF transformation matrices are 3x3 matrices containing the following values :
	//	
	//		| sx rx 0 |
	//		| ry sy 0 |
	//		| tx ty 1 |
	//
	//	However, we do not care about the 3rd column, which is always hardcoded. Transformation
	//	matrices here are implemented 6-elements arrays :
	//
	//		[ sx, rx, ry, tx, ty ]
	private function  __matrix_multiply ( $ma, $mb, $page_width, $page_height )
	   {
		// Scaling text is only appropriate for rendering graphics ; in our case, we just have to render 
		// basic text without any consideration about its width or height ; so adjust the sx/sy parameters
		// accordingly
		$scale_1x	=  ( $ma [0]  >  0 ) ?  1 : -1 ;
		$scale_1y	=  ( $ma [3]  >  0 ) ?  1 : -1 ;
		$scale_2x	=  ( $mb [0]  >  0 ) ?  1 : -1 ;
		$scale_2y	=  ( $mb [3]  >  0 ) ?  1 : -1 ;

		// Perform the matrix multiplication
		$r	=  array ( ) ;
		$r [0]	=  ( $scale_1x * $scale_2x ) + ( $ma [1] * $mb [2]   ) ;
		$r [1]	=  ( $scale_1x * $mb [1]   ) + ( $ma [1] * $scale_2y ) ;
		$r [2]	=  ( $scale_1y * $scale_2x ) + ( $scale_1y * $mb [2]   ) ;
		$r [3]	=  ( $scale_1y * $mb [1]   ) + ( $scale_1y* $scale_2y ) ;
		$r [4]	=  ( $ma [4]   * $scale_2x ) + ( $ma [5] * $mb [2]   ) + $mb [4] ;
		$r [5]	=  ( $ma [4]   * $mb [1]   ) + ( $ma [5] * $scale_2y ) + $mb [5] ;

		// Negative x/y values are expressed relative to the page width/height (???)
		if  ( $r [0]  <  0 )
			$r [4]	=  abs ( $r [4] ) ;//$page_width - $r [4] ;

		if  ( $r [3]  <  0 ) 
			$r [5]	=  abs ( $r [5] ) ; //$page_height - $r [5] ;

		return ( $r ) ;
	    }


	// __next_token_ex :
	//	Reviewed version of __next_token, adapted to ExtractTextWithLayout.
	//	Both functions will be unified when this one will be stabilized.
	private function  __next_token_ex ( $page_number, $data, $data_length, $index, &$token, &$next_index )
	   {
		// Skip spaces
		$count		=  0 ;

		while  ( $index  <  $data_length  &&  ( $data [ $index ]  ==  ' '  ||  $data [ $index ]  ==  "\t"  ||  $data [ $index ]  ==  "\r"  ||  $data [ $index ]  ==  "\n" ) )
		   {
			$index ++ ; 
			$count ++ ;
		    }

		$enhanced_statistics	=  $this -> EnhancedStatistics ;
		$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ 'space' ]	+=  $count ;

		// End of input
		if  ( $index  >=  $data_length )
			return ( false ) ;

		// The current character will tell us what to do
		$ch 	=  $data [ $index ] ;

		switch ( $ch )
		   {
			// Opening square bracket : we have to find the closing one, taking care of escape sequences
			// that can also specify a square bracket, such as "\]"
		   	case 	"[" :
		   		$next_index 	=  $index + 1 ;
		   		$parent 	=  0 ;
		   		$angle 		=  0 ;
		   		$token		=  '[' ;

		   		while  ( $next_index  <  $data_length )
		   		   {
		   			$nch 	=  $data [ $next_index ++ ] ;

		   			switch  ( $nch )
		   			   {
		   			   	case 	'(' :
		   			   		$parent ++ ;
		   			   		$token 	.=  $nch ;
		   			   		break ;

		   			   	case 	')' :
		   			   		$parent -- ;
		   			   		$token 	.=  $nch ;
		   			   		break ;

		   			   	case 	'<' :
							// Although the array notation can contain hex digits between angle brackets, we have to
							// take care that we do not have an angle bracket between two parentheses such as :
							// [ (<) ... ]
							if  ( ! $parent )
		   			   			$angle ++ ;

		   			   		$token 	.=  $nch ;
		   			   		break ;

		   			   	case 	'>' :
							if  ( ! $parent )
		   			   			$angle -- ;

		   			   		$token 	.=  $nch ;
		   			   		break ;

		   			   	case 	'\\' :
		   					$token 	.=  $nch . $data [ $next_index ++ ] ;
		   					break ;

		   			   	case 	']' :
		   					$token 	.=  ']' ;

		   					if  ( ! $parent  )
		   						break  2 ;
		   					else
		   						break ;

						case	"\n" :
						case	"\r" :
							break ;

		   			   	default :
		   			   		$token 	.=  $nch ;
		   			    }
		   		    }

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '[' ] ++ ;

		   		return ( true ) ;

			// Parenthesis : Again, we have to find the closing parenthesis, taking care of escape sequences
			// such as "\)"
		   	case 	"(" :
		   		$next_index 	=  $index + 1 ;
		   		$token		=  '[' . $ch ;

		   		while  ( $next_index  <  $data_length )
		   		   {
		   			$nch 	=  $data [ $next_index ++ ] ;

		   			if  ( $nch  ===  '\\' )
					   {
						$after		 =  $data [ $next_index ] ;

						// Character references specified as \xyz, where "xyz" are octal digits
						if  ( $after  >=  '0'  &&  $after  <=  '7' )
						   {
							$token		.=  $nch ;

							while  ( $data [ $next_index ]  >=  '0'  &&  $data [ $next_index ]  <=  '7' )
								$token		.=  $data [ $next_index ++ ] ;
						    }
						// Regular character escapes
						else
		   					$token 	.=  $nch . $data [ $next_index ++ ] ;
					    }
		   			else if  ( $nch  ===  ')' )
		   			   {
		   				$token 	.=  ')' ;
		   				break ;
		   			    }
		   			else
		   				$token 	.=  $nch ;
		   		   }

				$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '(' ] ++ ;
				$token	.=  ']' ;

		   		return ( true ) ;

			// A construction of the form : "<< something >>", or a unicode character
		   	case 	'<' :
				if  ( isset ( $data [ $index + 1 ] ) )
				   {
		   			if (  $data [ $index + 1 ]  ===  '<' )
		   			   {
		   		   		$next_index 	=  strpos ( $data, '>>', $index + 2 ) ;

		   				if  ( $next_index  ===  false )
		   					return ( false ) ;

						$token		=  substr ( $data, $index, $next_index - $index + 2 ) ;
						$next_index    +=  2 ;
		   				
						return ( true ) ;
		   			    }
		   			else
		   			   {
		   		   		$next_index 	=  strpos ( $data, '>', $index + 2 ) ;

		   				if  ( $next_index  ===  false )
		   					return ( false ) ;

						$enhanced_statistics  &&  $this -> Statistics [ 'Distributions' ] [ '<' ] ++ ;

						// There can be spaces and newlines inside a series of hex digits, so remove them...
						$result			=  preg_replace ( '/\s+/', '', substr ( $data, $index, $next_index - $index + 1 ) ) ;

						$token		=  "[$result]" ;
						$next_index ++ ;

		   				return ( true ) ;
		   			   }
				    }
				else 
					return ( false ) ;

			// Tick character : consider it as a keyword, in the same way as the "TJ" or "Tj" keywords
		   	case 	"'" :
			case	'"' :
		   		$token		 =  $ch ;
				$next_index	+=  2 ;

				return ( true ) ;

			// Other cases : this may be either a floating-point number or a keyword
		   	default :
		   		$next_index	=  ++ $index ;
		   		$token 		=  $ch ;

				if  ( isset ( $data [ $next_index ] ) )
				   {
		   			if ( ( $ch  >=  '0'  &&  $ch  <=  '9' )  ||  $ch  ==  '-'  ||  $ch  ==  '+'  ||  $ch  ==  '.' )
		   			   {
		   				while  ( $next_index  <  $data_length  &&
		   						( ( $data [ $next_index ]  >=  '0'  &&  $data [ $next_index ]  <=  '9' )  ||  
									$data [ $next_index ]  ===  '-'  ||  $data [ $next_index ]  ===  '+'  ||  $data [ $next_index ]  ===  '.' ) )
		   					$token 	.=  $data [ $next_index ++ ] ;
		   			    }
		   			else if  ( ( self::$CharacterClasses [ $ch ] & self::CTYPE_ALPHA )  ||  
							$ch  ==  '/'  ||  $ch  ==  '!' )
		   			   {
						$ch	=  $data [ $next_index ] ;

						while  ( $next_index  <  $data_length  &&  
							( ( self::$CharacterClasses [ $ch ] & self::CTYPE_ALNUM )  ||  
								$ch  ==  '*'  ||  $ch  ==  '-'  ||  $ch  ==  '_'  ||  $ch  ==  '.'  ||  $ch  ==  '+' ) )
						   {
							$token 	.=  $ch ;
							$next_index ++ ;

							if  ( isset ( $data [ $next_index ] ) )
								$ch	=  $data [ $next_index ] ;
						    }
		   			    }
				    }

		   		return ( true ) ;
		    }
	    }


	// __decode_text -
	//	Text decoding function when the PDFOPT_BASIC_LAYOUT flag is specified.
	private function  __decode_text ( $data, $current_font, $current_font_mapped, $current_font_map_width )
	   {
		list ( $text_values, $offsets ) 	=  $this -> __extract_chars_from_array ( $data ) ;
		$value_index				=  0 ;
		$result					=  '' ;

		// Fonts having character maps will require some special processing
		if  ( $current_font_mapped )
		   {
			// Loop through each text value
			foreach  ( $text_values  as  $text )
			   {
				$is_hex 	=  ( $text [0]  ==  '<' ) ;
				$length 	=  strlen ( $text ) - 1 ;
				$handled	=  false ;

				// Characters are encoded within angle brackets ( "<>" ).
				// Note that several characters can be specified within the same angle brackets, so we have to take
				// into account the width we detected in the begincodespancerange construct 
				if  ( $is_hex )
				   {
					for  ( $i = 1 ; $i  <  $length ; $i += $current_font_map_width )
					   {
						$value		 =  substr ( $text, $i, $current_font_map_width ) ;
						$ch 		 =  hexdec ( $value ) ;

						if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ) )
							$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ;
						else
						   {
							$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ch ) ;
							$this -> CharacterMapBuffer [ $current_font ] [ $ch ]		=  $newchar ;
						    }

						$result		.=  $newchar ;
					    }

					$handled	 =  true ;
				    }
				// Yes ! double-byte codes can also be specified as plain text within parentheses !
				// However, we have to be really careful here ; the sequence :
				//	(Be)
				// can mean the string "Be" or the Unicode character 0x4265 ('B' = 0x42, 'e' = 0x65)
				// We first look if the character map contains an entry for Unicode codepoint 0x4265 ;
				// if not, then we have to consider that it is regular text to be taken one character by
				// one character. In this case, we fall back to the "if ( ! $handled )" condition
				else if  ( $current_font_map_width  ==  4  )
				   {
					$temp_result		=  '' ;

					for  ( $i = 1 ; $i  <  $length ; $i ++ )
					   {
						// Each character in the pair may be a backslash, which escapes the next character so we must skip it
						// This code needs to be reviewed ; the same code is duplicated to handle escaped characters in octal notation
						if  ( $text [$i]  !=  '\\' )
							$ch1	=  $text [$i] ;
						else 
						   {
							$i ++ ;

							if  ( $text [$i]  <  '0'  ||  $text [$i]  >  '7' )
								$ch1	=  $this -> ProcessEscapedCharacter ( $text [$i] ) ;
							else
							   {
								$oct		=  '' ;
								$digit_count	=  0 ;

								while  ( $i  <  $length  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7'  &&  $digit_count  <  3 )
								   {
									$oct	.=  $text [$i ++] ;
									$digit_count ++ ;
								    }

								$ch1	=  chr ( octdec ( $oct ) ) ;
								$i -- ;
							    }
						    }

						$i ++ ;

						if  ( $text [$i]  != '\\' )
							$ch2	=  $text [$i] ;
						else
						   {
							$i ++ ;

							if  ( $text [$i]  <  '0'  ||  $text [$i]  >  '7' )
								$ch2	=  $this -> ProcessEscapedCharacter ( $text [$i] ) ;
							else
							   {
								$oct		=  '' ;
								$digit_count	=  0 ;

								while  ( $i  <  $length  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7'  &&  $digit_count  <  3 )
								   {
									$oct	.=  $text [$i ++] ;
									$digit_count ++ ;
								    }

								$ch2	=  chr ( octdec ( $oct ) ) ;
								$i -- ;
							    }
						    }

						// Build the 2-bytes character code
						$ch		=  ( ord ( $ch1 )  <<  8 )  |  ord ( $ch2 ) ;

						if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ) )
							$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ch ] ;
						else
						   {
							$newchar	=  $this -> FontTable -> MapCharacter ( $current_font, $ch, true ) ;
							$this -> CharacterMapBuffer [ $current_font ] [ $ch ]		=  $newchar ;
						    }

						// Yes !!! for characters encoded with two bytes, we can find the following construct :
						//	0x00 "\" "(" 0x00 "C" 0x00 "a" 0x00 "r" 0x00 "\" ")"
						// which must be expanded as : (Car)
						// We have here the escape sequences "\(" and "\)", but the backslash is encoded on two bytes
						// (although the MSB is nul), while the escaped character is encoded on 1 byte. waiting
						// for the next quirk to happen...
						if  ( $newchar  ==  '\\' )
						   {
							$newchar		=  $this -> ProcessEscapedCharacter ( $text [ $i + 2 ] ) ;
							$i ++ ;		// this time we processed 3 bytes, not 2
						    }

						$temp_result		.=  $newchar ;
					    }

					// Happens only if we were unable to translate a character using the current character map
					$result		.=  $temp_result ;
					$handled	 =  true ;
				    }

				// Character strings within parentheses.
				// For every text value, use the character map table for substitutions
				if  ( ! $handled )
				   {
					for  ( $i = 1 ; $i  <  $length ; $i ++ )
					   {
						$ch 		=  $text [$i] ;

						// Set to true to optimize calls to MapCharacters
						// Currently does not work with pobox@dizy.sk/infoma.pdf (a few characters differ)
						$use_map_buffer	=  false ;

						// ... but don't forget to handle escape sequences "\n" and "\r" for characters
						// 10 and 13
						if  ( $ch  ==  '\\' )
						   {
							$ch 	=  $text [++$i] ;

							// Escaped character
							if  ( $ch  <  '0'  ||  $ch  >  '7' )
								$ch		=  $this -> ProcessEscapedCharacter ( $ch ) ;
							// However, an octal form can also be specified ; in this case we have to take into account
							// the character width for the current font (if the character width is 4 hex digits, then we
							// will encounter constructs such as "\000\077").
							// The method used here is dirty : we build a regex to match octal character representations on a substring
							// of the text 
							else
							   {
								$width		=  $current_font_map_width / 2 ;	// Convert to byte count
								$subtext	=  substr ( $text, $i - 1 ) ;
								$regex		=  "#^ (\\\\ [0-7]{3}){1,$width} #imsx" ;

								$status		=  preg_match ( $regex, $subtext, $octal_matches ) ;

								if  ( $status )
								   {
									$octal_values	=  explode ( '\\', substr ( $octal_matches [0], 1 ) ) ;
									$ord		=  0 ;

									foreach  ( $octal_values  as  $octal_value ) 
										$ord	=  ( $ord  <<  8 ) + octdec ( $octal_value ) ;

									$ch	 =  chr ( $ord ) ;
									$i	+=  strlen ( $octal_matches [0] ) - 2 ;
								    }
							    }

							$use_map_buffer		=  false ;
						    }

						// Add substituted character to the output result
						$ord		 =  ord ( $ch ) ;

						if  ( ! $use_map_buffer )
							$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
						else
						   {
							if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ) )
								$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ;
							else
							   {
								$newchar	 =  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
								$this -> CharacterMapBuffer [ $current_font ] [ $ord ]	=  $newchar ;
							    }
						    }

						$result		.=  $newchar ;
					    }
				    }

				// Handle offsets between blocks of characters
				if  ( isset ( $offsets [ $value_index ] )  &&
						- ( $offsets [ $value_index ] )  >  $this -> MinSpaceWidth )
					$result		.=  $this -> __get_character_padding ( $offsets [ $value_index ] ) ;

				$value_index ++ ;
			    }
		    }
		// For fonts having no associated character map, we simply encode the string in UTF8
		// after the C-like escape sequences have been processed
		// Note that <xxxx> constructs can be encountered here, so we have to process them as well
		else
		   {
			foreach  ( $text_values  as  $text )
			   {
				$is_hex 	=  ( $text [0]  ==  '<' ) ;
				$length 	=  strlen ( $text ) - 1 ;

				// Some text within parentheses may have a backslash followed by a newline, to indicate some continuation line.
				// Example :
				//	(this is a sentence \
				//	 continued on the next line)
				// Funny isn't it ? so remove such constructs because we don't care
				$text		=  str_replace ( array ( "\\\r\n", "\\\r", "\\\n" ), '', $text ) ;

				// Characters are encoded within angle brackets ( "<>" )
				if  ( $is_hex )
				   {
					for  ( $i = 1 ; $i  <  $length ; $i += 2 )
					   {
						$ch 	=  hexdec ( substr ( $text, $i, 2 ) ) ;

						$result .=  $this -> CodePointToUtf8 ( $ch ) ;
					    }
				    }
				// Characters are plain text
				else
				   {
					$text	=  self::Unescape ( $text ) ;

					for  ( $i = 1, $length = strlen ( $text ) - 1 ; $i  <  $length ; $i ++ )
					   {
						$ch	=  $text [$i] ;
						$ord	=  ord ( $ch ) ;

						if  ( $ord  <  127 )
							$newchar	=  $ch ;
						else
						   {
							if  ( isset ( $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ) )
								$newchar	=  $this -> CharacterMapBuffer [ $current_font ] [ $ord ] ;
							else
							   {
								$newchar	=  $this -> FontTable -> MapCharacter ( $current_font, $ord ) ;
								$this -> CharacterMapBuffer [ $current_font ] [ $ord ]	=  $newchar ;
							    }
						    }

						$result		.=  $newchar ;
					    }
				    }

				// Handle offsets between blocks of characters
				if  ( isset ( $offsets [ $value_index ] )  &&
						abs ( $offsets [ $value_index ] )  >  $this -> MinSpaceWidth )
					$result		.=  $this -> __get_character_padding ( $offsets [ $value_index ] ) ;

				$value_index ++ ;
			   }
		    }

		// All done, return
		return ( $result ) ;
	    }


	// __assemble_text_fragments -
	//	Assembles text fragments collected by the ExtractTextWithLayout function.
	private function  __assemble_text_fragments ( $page_number, &$fragments, &$page_width, &$page_height )
	   {
		$fragment_count		=  count ( $fragments ) ;

		// No fragment no cry...
		if  ( ! $fragment_count )
			return ( '' ) ;

		// Compute the width of each fragment
		foreach  ( $fragments  as  &$fragment )
			$this -> __compute_fragment_width ( $fragment ) ;

		// Sort the fragments and group them by line
		usort ( $fragments, array ( $this, '__sort_page_fragments' ) ) ;
		$line_fragments		=  $this -> __group_line_fragments ( $fragments ) ;

		// Retrieve the page attributes 
		$page_attributes	=  $this -> PageMap -> PageAttributes [ $page_number ] ;

		// Some buggy PDF do not specify page width or page height so, during the processing of text fragments,
		// page width & height will be set to the largest x/y coordinate
		if  ( isset ( $page_attributes [ 'width' ] )  &&  $page_attributes [ 'width' ] )
			$page_width		=  $page_attributes [ 'width' ] ;
		else
		   {
			$page_width		=  0 ;
			
			foreach  ( $fragments  as  $fragment )
			   {
				$end_x	=  $fragment [ 'x' ] + $fragment [ 'width' ] ;

				if  ( $end_x  >  $page_width )
					$page_width	=  $end_x ;
			    }
		    }

		if  ( isset ( $page_attributes [ 'height' ] )  &&  $page_attributes [ 'height' ] )
			$page_height		=  $page_attributes [ 'height' ] ;
		else
			$page_height		=  $fragments [0] [ 'y' ] ;

		// Block separator
		$separator			=  ( $this -> BlockSeparator ) ?  $this -> BlockSeparator : ' ' ;

		// Unprocessed marker count
		$unprocessed_marker_count	=  count ( $this -> UnprocessedMarkerList [ 'font' ] ) ;

		// Add page information if the PDFOPT_DEBUG_SHOW_COORDINATES option has been specified
		if  ( $this -> Options  &  self::PDFOPT_DEBUG_SHOW_COORDINATES )
			$result		=  "[Page : $page_number, width = $page_width, height = $page_height]" . $this -> EOL ;
		else 
			$result			=  '' ;

		// Loop through each line of fragments
		for  ( $i = 0, $line_count = count ( $line_fragments ) ; $i  <  $line_count ; $i ++ )
		   {
			$current_x	=  0 ;

			// Loop through each fragment of the current line
			for  ( $j = 0, $fragment_count = count ( $line_fragments [$i] ) ; $j  <  $fragment_count ; $j ++ )
			   {
				$fragment	=  $line_fragments [$i] [$j] ;

				// Process the markers which do not have an associated font yet - this will be done by matching
				// the current text fragment against one of the regular expressions defined.
				// If a match occurs, then all the subsequent text fragment using the same font will be put markers
				for  ( $k = 0 ; $k  <  $unprocessed_marker_count ; $k ++ )
					{
					$marker			=  $this -> UnprocessedMarkerList [ 'font' ] [$k] ;

					if  ( preg_match ( $marker [ 'regex' ], $fragment [ 'text' ] ) )
					   {
						$this -> TextWithFontMarkers [ $fragment [ 'font' ] ]	=  array
							(
							'font'		=>  $fragment [ 'font'  ],
							'height'	=>  $fragment [ 'font-height' ],
							'regex'		=>  $marker   [ 'regex' ],
							'start'		=>  $marker   [ 'start' ],
							'end'		=>  $marker   [ 'end'   ] 
							) ;

						$unprocessed_marker_count -- ;
						unset ( $this -> UnprocessedMarkerList [ 'font' ] [$k] ) ;

						break ;
					    }
					}

				// Add debug info if needed 
				if  ( $this -> Options & self::PDFOPT_DEBUG_SHOW_COORDINATES )
					$result		.=  $this -> __debug_get_coordinates ( $fragment ) ;

				// Add a separator between two fragments, if needed
				if  ( $j )
				   {
					if  ( $current_x  <  floor ( $fragment [ 'x' ] ) )	// Accept small rounding errors
						$result		.=  $separator ;
				    }

				// Check if we need to add markers around this text fragment
				if  ( isset ( $this -> TextWithFontMarkers [ $fragment [ 'font' ] ] )  &&
						$this -> TextWithFontMarkers [ $fragment [ 'font' ] ] [ 'height' ]  ==  $fragment [ 'font-height' ] )
				    {
					$fragment_text	=  $this -> TextWithFontMarkers [ $fragment [ 'font' ] ] [ 'start' ] .
							   $fragment [ 'text' ] .
							   $this -> TextWithFontMarkers [ $fragment [ 'font' ] ] [ 'end' ] ;
				     }
				else 
					$fragment_text  =  $fragment [ 'text' ] ;

				// Add the current fragment to the result
				$result		.=  $fragment_text ;

				// Update current x-position
				$current_x	=  $fragment [ 'x' ] + $fragment [ 'width' ] ;
			    }

			// Add a line break between each line
			$result		.=  $this -> EOL ;
		    }

		// All done, return
		return ( $result ) ;
	    }


	// __sort_page_fragments -
	//	Sorts page fragments by their (y,x) coordinates.
	public function  __sort_page_fragments ( $a, $b )
	   {
		$xa			=  $a [ 'x' ] ;
		$ya			=  $a [ 'y' ] ;
		$xb			=  $b [ 'x' ] ;
		$yb			=  $b [ 'y' ] ;

		if  ( $ya  !==  $yb )
			return ( $yb - $ya ) ;
		else
			return ( $xa - $xb ) ;
	    }


	// __sort_line_fragments -
	//	Sorts fragments per line.
	public function  __sort_line_fragments ( $a, $b )
	   {
		return ( $a [ 'x' ] - $b [ 'x' ] ) ;
	    }


	// __group_line_fragments -
	//	Groups page fragments per line, allowing a certain variation in the y-position.
	private function  __group_line_fragments ( $fragments )
	   {
		$result			=  array ( ) ;
		$fragment_count		=  count ( $fragments ) ;
		$last_y_coordinate	=  $fragments [0] [ 'y' ] ;
		$current_fragments	=  array ( $fragments [0] ) ;

		for  ( $i = 1 ; $i  <  $fragment_count ; $i ++ )
		   {
			$fragment	=  $fragments [$i] ;

			if  ( $fragment [ 'y' ] + $fragment [ 'font-height' ]  >=  $last_y_coordinate )
				$current_fragments []	=  $fragment ;
			else
			   {
				$last_y_coordinate	=  $fragment [ 'y' ] ;
				usort ( $current_fragments, array ( $this, '__sort_line_fragments' ) ) ;
				$result	[]		=  $current_fragments ;
				$current_fragments	=  array ( $fragment ) ;
			    }
		    }

		if  ( count ( $current_fragments ) )
		   {
			usort ( $current_fragments, array ( $this, '__sort_line_fragments' ) ) ;
			$result []	=  $current_fragments ;
		    }

		return ( $result ) ;
	    }


	// __compute_fragment_width -
	//	Compute the width of the specified text fragment and add the width entry accordingly.
	//	Returns the font object associated with this fragment
	private function  __compute_fragment_width ( &$fragment )
	   {
		// To avoid repeated calls to the PdfTexterFontTable::GetFontObject() method, we are buffering them in the FontObjectsBuffer property.
		$object_reference	=  $fragment [ 'page' ] . ':' . $fragment [ 'template' ] . ':' . $fragment [ 'font' ] ;

		if  ( isset ( $this -> FontObjectsBuffer [ $object_reference ] ) ) 
			$font_object	=  $this -> FontObjectsBuffer [ $object_reference ] ;
		else
		   {
			$font_object		=  $this -> FontTable -> GetFontObject ( $fragment [ 'page' ], $fragment [ 'template' ], $fragment [ 'font' ] ) ;
			$this -> FontObjectsBuffer [ $object_reference ]	=  $font_object ;
		    }

		// The width of the previous text fragment will be computed only if its associated font contains character widths information
		$fragment [ 'width' ]		=  ( $font_object ) ?  $font_object -> GetStringWidth ( $fragment [ 'text' ], $this -> ExtraTextWidth ) : 0 ;

		// Return the font object
		return ( $font_object ) ;
	    }


	// __debug_get_coordinates -
	//	Returns the coordinates of the specified text fragment, in debug mode.
	private function  __debug_get_coordinates ( $fragment )
	   {
		return ( "\n[x:" . round ( $fragment [ 'x' ], 3 ) . ', y:' . round ( $fragment [ 'y' ], 3 ) . 
			 ", w: " . round ( $fragment [ 'width' ], 3 ) . ", h:" . round ( $fragment [ 'font-height' ], 3 ) . ", font:" . $fragment [ 'font' ] . "]" ) ;
	    }
 

	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetTrailerInformation - Retrieves trailer information.
	
	    PROTOTYPE
	        $this -> GetTrailerInformation ( $contents ) ;
	
	    DESCRIPTION
	        Retrieves trailer information :
		- Unique file ID
		- Id of the object containing encryption data, if the PDF file is encrypted
		- Encryption data
	
	    PARAMETERS
	        $contents (string) -
	                PDF file contents.

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetTrailerInformation ( $contents, $pdf_objects )
	   {
		// Be paranoid : check if there is trailer information
		if  ( ! preg_match ( '/trailer \s* << (?P<trailer> .+?) >>/imsx', $contents, $trailer_match ) )
			return ;

		$trailer_data	=  $trailer_match [ 'trailer' ] ;

		// Get the unique file id from the trailer data
		static		$id_regex	=  '#
							/ID \s* \[ \s*
							< (?P<id1> [^>]+) >
							\s*
							< (?P<id2> [^>]+) >
							\s* \]
						    #imsx' ;

		if  ( preg_match ( $id_regex, $trailer_data, $id_match ) )
		   {
			$this -> ID	=  $id_match [ 'id1' ] ;
			$this -> ID2	=  $id_match [ 'id2' ] ;
		    }

		// If there is an object describing encryption data, get its number (/Encrypt flag)
		if (  ! preg_match ( '#/Encrypt \s+ (?P<object> \d+)#ix', $trailer_data, $encrypt_match ) )
			return ;

		$encrypt_object_id	=  $encrypt_match [ 'object' ] ;
		
		if  ( ! isset ( $pdf_objects [ $encrypt_object_id ] ) )
		   {
			if  ( self::$DEBUG )
				error ( new PdfToTextDecodingException ( "Object #$encrypt_object_id, which should contain encryption data, is missing." ) ) ;

			return ;
		    }

		// Parse encryption information
		$this -> EncryptionData		=  PdfEncryptionData::GetInstance ( $this -> ID, $encrypt_object_id, $pdf_objects [ $encrypt_object_id ] ) ;
		$this -> IsEncrypted		=  ( $this -> EncryptionData  !==  false ) ;
	    }


	// __build_ignored_instructions :
	//	Takes the template regular expressions from the self::$IgnoredInstructionsTemplates, replace each string with the contents
	//	of the self::$ReplacementConstructs array, and sets the self::$IgnoredInstructions to a regular expression that is able to
	//	match the Postscript instructions to be removed from any text stream.
	private function  __build_ignored_instructions ( )
	   {
		$searches	=  array_keys   ( self::$ReplacementConstructs ) ;
		$replacements	=  array_values ( self::$ReplacementConstructs ) ;

		foreach  ( self::$IgnoredInstructionTemplatesLayout  as  $template )
		   {
			$template	=  '/' . str_replace ( $searches, $replacements, $template ) . '/msx' ;

			self::$IgnoredInstructionsLayout []	=  $template ;
			self::$IgnoredInstructionsNoLayout []	=  $template ;
		    }

		foreach  ( self::$IgnoredInstructionTemplatesNoLayout  as  $template )
		   {
			$template	=  '/' . str_replace ( $searches, $replacements, $template ) . '/msx' ;

			self::$IgnoredInstructionsNoLayout []	=  $template ;
		    }
	    }


	// __convert_utf16 :
	//	Some strings found in a pdf file can be encoded in UTF16 (author information, for example).
	//	When this is the case, the string is converted to UTF8.
	private function  __convert_utf16 ( $text )
	   {
		if  ( isset ( $text [0] )  &&  isset ( $text [1] ) )
		   {
			$b1	=  ord ( $text [0] ) ;
			$b2	=  ord ( $text [1] ) ;

			if  ( ( $b1  ==  0xFE  &&  $b2  ==  0xFF )  ||  ( $b1  ==  0xFF  &&  $b2  ==  0xFE ) )
				$text	=  mb_convert_encoding ( $text, 'UTF-8', 'UTF-16' ) ;
		    }

		return ( $text ) ;
	    }


	// __extract_chars_from_array -
	//	Extracts characters enclosed either within parentheses (character codes) or angle brackets (hex value)
	//	from an array.
	//	Example :
	//
	//		[<0D>-40<02>-36<03>-39<0E>-36<0F>-36<0B>-37<10>-37<10>-35(abc)]
	//
	// 	will return an array having the following entries :
	//
	//		<0D>, <02>, <03>, <0E>, <0F>, <0B>, <10>, <10>, (abc)
	private function  __extract_chars_from_array ( $array )
	   {
		$length 	=  strlen ( $array ) - 1 ;
		$result 	=  array ( ) ;
		$offsets	=  array ( ) ; 

		for  ( $i = 1 ; $i  <  $length ; $i ++ )	// Start with character right after the opening bracket
		   {
		   	$ch 	=  $array [$i] ;

			if  ( $ch  ==  '(' )
				$endch 	=  ')' ;
			else if  ( $ch  ==  '<' )
				$endch 	=  '>' ;
			else
			   {
				$value	=  '' ;

				while  ( $i  <  $length  &&  ( ( $array [$i]  >=  '0'  &&  $array [$i]  <=  '9' )  ||  
						$array [$i]  ==  '-'  ||  $array [$i]  ==  '+'  ||  $array [$i]  ==  '.' ) )
					$value	.=  $array [$i++] ;

				$offsets []	=  ( double ) $value ;

				if  ( $value  !==  '' )
					$i -- ;

				continue ;
			    }

			$char 	=  $ch ;
			$i ++ ;

			while  ( $i  <  $length  &&  $array [$i]  !=  $endch )
			   {
			   	if  ( $array [$i]  ==  '\\' )
			   		$char 	.=  '\\' . $array [++$i] ;
				else
				   {
					$char 	.=  $array [$i] ;

					if  ( $array [$i]  ==  $endch )
						break ;
				    }

				$i ++ ;
			   }

			$result [] 	 =  $char . $endch ;
		    }

		return ( array ( $result, $offsets ) ) ;
	    }


	// __extract_chars_from_block -
	//	Extracts characters from a text block (enclosed in parentheses).
	//	Returns an array of character ordinals if the $as_array parameter is true, or a string if false.
	private function  __extract_chars_from_block ( $text, $start_index = false, $length = false, $as_array = false )
	   {
		if  ( $as_array ) 
			$result		=  array ( ) ;
		else
			$result		=  '' ;

		if  ( $start_index  ===  false )
			$start_index	=  0 ;

		if  ( $length  ===  false )
			$length		=  strlen ( $text ) ;

		$ord0	=  ord ( '0' ) ;

		for  ( $i = $start_index ; $i  <  $length ; $i ++ )
		   {
			$ch	=  $text [$i] ;

			if  ( $ch  ==  '\\' )
			   {
				if  ( isset ( $text [ $i + 1 ] ) )
				   {
					$ch2	=  $text [ ++$i ] ;

					switch  ( $ch2 )
					   {
						case  'n' :  $ch =  "\n" ; break ;
						case  'r' :  $ch =  "\r" ; break ;
						case  't' :  $ch =  "\t" ; break ;
						case  'f' :  $ch =  "\f" ; break ;
						case  'v' :  $ch =  "\v" ; break ;

						default :
							if  ( $ch2  >=  '0'  &&  $ch2  <=  '7' )
							   {
								$ord	=  $ch2 - $ord0 ;
								$i ++ ;

								while  ( isset ( $text [$i] )  &&  $text [$i]  >=  '0'  &&  $text [$i]  <=  '7' )
								   {
									$ord	=  ( $ord * 8 ) + ord ( $text [$i] ) - $ord0 ;
									$i ++ ;
								    }

								$ch	=  chr ( $ord ) ;
								$i -- ;
							    }
							else
								$ch	=  $ch2 ;

					    }
				    }
			    }

			if  ( $as_array )
				$result []	 =  ord ( $ch ) ;
			else
				$result		.=  $ch ;
		    }

		return ( $result ) ;
	    }


	// __get_character_padding :
	//	If the offset specified between two character groups in an array notation for displaying text is less
	//	than -MinSpaceWidth thousands of text units, 
	private function  __get_character_padding ( $char_offset )
	   {
		if  ( $char_offset  <=  - $this -> MinSpaceWidth )
		   {
			if  ( $this -> Options & self::PDFOPT_REPEAT_SEPARATOR )
			   {
				// If the MinSpaceWidth property is less than 1000 (text units), consider it has the value 1000
				// so that an exuberant number of spaces will not be repeated
				$space_width	=  ( $this -> MinSpaceWidth  <  1000 ) ?  1000 :  $this -> MinSpaceWidth ;

				$repeat_count	=  abs ( round ( $char_offset / $space_width, 0 ) ) ;

				if  ( $repeat_count )
					$padding	=  str_repeat ( $this -> Separator, $repeat_count ) ;
				else
					$padding	=  $this -> Separator ;
				}
			else 
				$padding	=  $this -> Separator ;

			return ( utf8_encode ( self::Unescape ( $padding ) ) ) ;
		    }
		else
			return ( '' ) ;
	    }


	// __get_output_image_filename -
	//	Returns a real filename based on a template supplied by the AutoSaveImageFileTemplate property.
	private function  __get_output_image_filename ( )
	   {
		static		$suffixes	=  array
		   (
			IMG_JPEG		=>  'jpg',
			IMG_JPG			=>  'jpg',
			IMG_GIF			=>  'gif',
			IMG_PNG			=>  'png',
			IMG_WBMP		=>  'wbmp',
			IMG_XPM			=>  'xpm'
		    ) ;

		$template	=  $this -> ImageAutoSaveFileTemplate ;
		$length		=  strlen ( $template ) ;
		$parts		=  pathinfo ( $this -> Filename ) ;

		if  ( ! isset ( $parts [ 'filename' ] ) )	// for PHP versions < 5.2
		   {
			$index		=  strpos ( $parts [ 'basename' ], '.' ) ;

			if  ( $index  ===  false )
				$parts [ 'filename' ]	=  $parts [ 'basename' ] ;
			else
				$parts [ 'filename' ]	=  substr ( $parts [ 'basename' ], $index ) ;
		    }

		$searches	=  array ( ) ;
		$replacements	=  array ( ) ;

		// Search for each construct starting with '%'
		for  ( $i = 0 ; $i  <  $length ; $i ++ )
		   {
			if  ( $template [$i]  !=  '%'  ||  $i + 1  >=  $length )
				continue ;

			$ch	=  $template [ ++ $i ] ;

			// Percent sign found : check the character after
			switch  ( $ch ) 
			   {
				// "%%" : Replace it with a single percent
				case	'%' :
					$searches []		=  '%%' ;
					$replacements []	=  '%' ;
					break ;

				// "%p" : Path of the original PDF file
				case	'p' :
					$searches []		=  '%p' ;
					$replacements []	=  $parts [ 'dirname' ] ;
					break ;

				// "%f" : Filename part of the original PDF file, without its suffix
				case	'f' :
					$searches []		=  '%f' ;
					$replacements []	=  $parts [ 'filename' ] ;
					break ;

				// "%s" : Output image file suffix, determined by the ImageAutoSaveFormat property
				case	's' :
					if  ( isset ( $suffixes [ $this -> ImageAutoSaveFormat ] ) )
					   {
						$searches []		=  '%s' ;
						$replacements []	=  $suffixes [ $this -> ImageAutoSaveFormat ] ;
					    }
					else
					   {
						$searches []		=  '%s' ;
						$replacements []	=  'unknown' ;
					    }

					break ;

				// Other : may be either "%d", or "%xd", where "x" are digits expression the width of the final sequential index
				default :
					$width	=  0 ;
					$chars	=  '' ;

					if  ( ctype_digit ( $ch ) )
					   {
						do
						   {
							$width	 =  ( $width * 10 ) + ord ( $ch ) - ord ( '0' ) ;
							$chars  .=  $ch ;
							$i ++ ;
						    }  while  ( $i  <  $length  &&  ctype_digit ( $ch = $template [$i] ) ) ;

						if  ( $template [$i]  ==  'd' )
						   {
							$searches []		=  '%' . $chars . 'd' ;
							$replacements []	=  sprintf ( "%0{$width}d", $this -> ImageCount ) ;
						    }
					    }
					else
					   {
						$searches []		=  '%d' ;
						$replacements []	=  $this -> ImageCount ;
					    }
			    }
		    }

		// Perform the replacements
		if  ( count ( $searches ) )
			$result		=  str_replace ( $searches, $replacements, $template ) ;
		else
			$result		=  $template ;

		// All done, return 
		return ( $result ) ;
	    }


	// __rtl_process -
	//	Processes the contents of a page when it contains characters belonging to an RTL language.
	private function  __rtl_process ( $text )
	   {
		$length		=  strlen ( $text ) ;
		$pos		=  strcspn ( $text, self::$RtlCharacterPrefixes ) ;

		// The text does not contain any of the UTF-8 prefixes that may introduce RTL contents :
		// simply return it as is
		if   ( $pos  ==  $length  ||  $text [$pos]  ===  "\x00" )
			return ( $text ) ;

		// Extract each individual line, and get rid of carriage returns if any
		$lines		=  explode ( "\n", str_replace ( "\r", '', $text ) ) ;
		$new_lines	=  array ( ) ;

		// Loop through lines
		foreach  ( $lines  as  $line )
		   {
			// Check if the current line contains potential RTL characters
			$pos		=  strcspn ( $line, self::$RtlCharacterPrefixes ) ;
			$length		=  strlen ( $line ) ;

			// If not, simply store it as is
			if  ( $pos  ==  $length )
			   {
				$new_lines []	=  $line ;
				continue ;
			    }

			// Otherwise, it gets a little bit more complicated ; we have :
			// - To process each series of RTL characters and put them in reverse order
			// - Mark spaces and punctuation as "RTL separators", without reversing them (ie, a string like " ." remains " .", not ". ")
			// - Other sequences of non-RTL characters must be preserved as is and are not subject to reordering
			// The reordering sequence will be described later. For the moment, the $words array is used to store arrays of two elements :
			// - The first one is a boolean indicating whether it concerns RTL characters (true) or not (false)
			// - The second one is the string itself
			$words		=  array ( ) ;

			// Start of the string is not an RTL sequence ; we can add it to our $words array
			if  ( $pos )
			   {
				$word		=  substr ( $line, 0, $pos ) ;
				$words []	=  array ( $this -> __is_rtl_separator ( $word ), $word ) ;
			    }

			$in_rtl		=  true ;
	
			// Loop through remaining characters of the current line
			while  ( $pos  <  $length )
			   {
				// Character at the current position may be RTL character
				if  ( $in_rtl )
				   {

					$rtl_text		=  '' ;
					$rtl_char		=  '' ;
					$rtl_char_length	=  0 ;
					$found_rtl		=  false ;

					// Collect all the consecutive RTL characters, which represent a word, and put the letters in reverse order
					while  ( $pos  <  $length  &&  $this -> __is_rtl_character ( $line, $pos, $rtl_char, $rtl_char_length ) )
					   {
						$rtl_text	 =  $rtl_char . $rtl_text ;
						$pos		+=  $rtl_char_length ;
						$found_rtl	 =  true ;
					    }

					// ... but make sure that we found a valid RTL sequence
					if  ( $found_rtl )
						$words []	 =  array ( true, $rtl_text ) ;
					else
						$words []	 =  array ( false, $line [ $pos ++ ] ) ;

					// For now, we are no more in a series of RTL characters
					$in_rtl		=  false ;
				    }
				// Non-RTL characters : collect them until either the end of the current line or the next RTL character
				else
				   {
					$next_pos	=  $pos + strcspn ( $line, self::$RtlCharacterPrefixes, $pos ) ;

					if  ( $next_pos  >=  $length )
					   {
						$word		=  substr ( $line, $pos ) ;
						break ;
					    }
					else
					   {
						$word		=  substr ( $line, $pos, $next_pos - $pos ) ;
						$pos		=  $next_pos ;
						$in_rtl		=  true ;
					    }

					// Don't forget to make the distinction between a sequence of spaces and punctuations, and a real
					// piece of text. Space/punctuation strings surrounded by RTL words will be interverted
					$words []		=  array ( $this -> __is_rtl_separator ( $word ), $word ) ;
				    }
			    }

			// Now we have an array, $words, whose first entry of each element indicates whether the second entry is an RTL string
			// or not (this includes strings that contain only spaces and punctuation).
			// We have to gather all the consecutive array items whose first entry is true, then invert their order.
			// Non-RTL strings are not affected by this process.
			$stacked_rtl_words	=  array ( ) ;
			$new_words		=  array ( ) ;

			foreach  ( $words  as  $word )
			   {
				// RTL word : put it onto the stack
				if  ( $word [0] )
					$stacked_rtl_words []	=  $word [1] ;
				// Non-RTL word : add it as is to the output array, $new_words
				else
				   {
					// But if RTL words were stacked before, invert them and add them to the output array
					if  ( count ( $stacked_rtl_words ) )
					   {
						$new_words		=  array_merge ( $new_words, array_reverse ( $stacked_rtl_words ) ) ;
						$stacked_rtl_words	=  array ( ) ;
					    }

					$new_words []	=  $word [1] ;
				    }
			    }

			// Process any remaining RTL words that may have been stacked and not yet processed
			if  ( count ( $stacked_rtl_words ) )
				$new_words		=  array_merge ( $new_words, array_reverse ( $stacked_rtl_words ) ) ;

			// That's ok, we have processed one more line
			$new_lines []	=  implode ( '', $new_words ) ;
		    }

		// All done, return a catenation of all the lines processed so far
		$result		=  implode ( "\n", $new_lines ) ;
		
		return ( $result ) ;
	    }


	// __is_rtl_character -
	//	Checks if the sequence starting at $pos in string $text is a character belonging to an RTL language.
	//	If yes, returns true and sets $rtl_char to the UTF8 string sequence for that character, and $rtl_char_length
	//	to the length of this string.
	//	If no, returns false.
	private function  __is_rtl_character ( $text, $pos, &$rtl_char, &$rtl_char_length )
	   {
		$ch	=  $text [ $pos ] ;

		// Check that the current character is the start of a potential UTF8 RTL sequence
		if  ( isset  ( self::$RtlCharacterPrefixLengths [ $ch ] ) )
		   {
			// Get the number of characters that are expected after the sequence
			$length_after	=  self::$RtlCharacterPrefixLengths [ $ch ] ;

			// Get the sequence after the UTF8 prefix
			$codes_after	=  substr ( $text, $pos + 1, $length_after ) ;

			// Search through $RtlCharacters, which contains arrays of ranges related to the UTF8 character prefix
			foreach  ( self::$RtlCharacters [ $ch ]  as  $range )
			   {
				if  ( strcmp ( $range [0], $codes_after )  <=  0  &&  
				      strcmp ( $range [1], $codes_after )  >=  0 )
				   {
					$rtl_char		=  $ch . $codes_after ;
					$rtl_char_length	=  $length_after + 1 ;

					return ( true ) ;
				}
			    } 
			    
			return ( false ) ;
		    }
		else
			return ( false ) ;
	    }


	// __is_rtl_separator -
	//	RTL words are separated by spaces and punctuation signs that are specified as LTR characters.
	//	However, such sequences, which are separators between words, must be considered as being part 
	//	of an RTL sequence of words and therefore be reversed with them.
	//	This function helps to determine if the supplied string is simply a sequence of spaces and
	//	punctuation (a word separator) or plain text, that must keep its position in the line.
	private function  __is_rtl_separator ( $text )
	   {
		static		$known_separators	=  array ( ) ;
		static		$separators		=  " \t,.;:/!-_=+" ;

		if  ( isset ( $known_separators [ $text ] ) )
			return ( true ) ;

		for  ( $i = 0, $length = strlen ( $text ) ; $i  <  $length ; $i ++ )
		   {
			if  ( strpos ( $separators, $text [$i] )  ===  false )
				return ( false ) ;
		    }

		$known_separators [ $text ]	=  true ;

		return ( true ) ;
	    }


	// __strip_useless_instructions :
	//	Removes from a text stream all the Postscript instructions that are not meaningful for text extraction
	//	(these are mainly shape drawing instructions).
	private function  __strip_useless_instructions ( $data )
	   {
		$result		=  preg_replace ( $this -> IgnoredInstructions, ' ', $data ) ;

		$this -> Statistics [ 'TextSize' ]		+=  strlen ( $data ) ;
		$this -> Statistics [ 'OptimizedTextSize' ]	+=  strlen ( $result ) ;

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        IsPageSelected - Checks if a page is selected for output.
	
	    PROTOTYPE
	        $status		=  $this -> IsPageSelected ( $page ) ;
	
	    DESCRIPTION
	        Checks if the specified page is to be selected for output.
	
	    PARAMETERS
	        $page (integer) -
	                Page to be checked.
	
	    RETURN VALUE
	        True if the page is to be selected for output, false otherwise.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  IsPageSelected ( $page )
	   {
		if  ( ! $this -> MaxSelectedPages )
			return ( true ) ;
		
		if  ( $this -> MaxSelectedPages  >  0 )
			return  ( $page  <=  $this -> MaxSelectedPages ) ;

		// MaxSelectedPages  <  0 
		return ( $page  >  count ( $this -> PageMap -> Pages ) + $this -> MaxSelectedPages ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        PeekAuthorInformation - Gets author information from the specified object data.
	
	    PROTOTYPE
	        $this -> PeekAuthorInformation ( $object_id, $object_data ) ;
	
	    DESCRIPTION
	        Try to check if the specified object data contains author information (ie, the /Author, /Creator, 
		/Producer, /ModDate, /CreationDate keywords) and sets the corresponding properties accordingly.
	
	    PARAMETERS
	    	$object_id (integer) -
	    		Object id of this text block.

	    	$object_data (string) -
	    		Stream contents.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  PeekAuthorInformation ( $object_id, $object_data )
	   {
		if  ( ( strpos  ( $object_data, '/Author' )  !==  false  ||  strpos ( $object_data, '/CreationDate' )  !==  false ) )
		   {
			$this -> GotAuthorInformation	=  true ;
			return ( $object_id ) ;
		    }
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        RetrieveAuthorInformation - Extracts author information
	
	    PROTOTYPE
	        $this -> RetriveAuthorInformation ( $object_id, $pdf_objects ) ;
	
	    DESCRIPTION
	        Extracts the author information. Handles the case where flag values refer to existing objects.
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the object containing the author information.

		$pdf_objects (array) -
			Array whose keys are the PDF object ids, and values their corresponding contents.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  RetrieveAuthorInformation ( $object_id, $pdf_objects )
	   {
		static		$re		=  '#
							(?P<info>
								/
								(?P<keyword> (Author) | (Creator) | (Producer) | (Title) | (CreationDate) | (ModDate) | (Keywords) | (Subject) )
								\s* 
								(?P<opening> [(<])
							)
						    #imsx' ;
		static		$object_re	=  '#
							(?P<info>
								/
								(?P<keyword> (Author) | (Creator) | (Producer) | (Title) | (CreationDate) | (ModDate) | (Keywords) | (Subject) )
								\s* 
								(?P<object_ref>
									(?P<object> \d+)
									\s+
									\d+
									\s+
									R
								 )
							)
						    #imsx' ;

		// Retrieve the object data corresponding to the specified object id
		$object_data	=  $pdf_objects [ $object_id ] ;

		// Pre-process flags whose values refer to existing objects
		if  ( preg_match_all ( $object_re, $object_data, $object_matches ) )
		   {
			$searches		=  array ( ) ;
			$replacements		=  array ( ) ;

			for  ( $i = 0, $count = count ( $object_matches [ 'keyword' ] ) ; $i  <  $count ; $i ++ )
			   {
				$searches []		=  $object_matches [ 'object_ref' ] [$i] ;

				// Some buggy PDF may reference author information objects that do not exist
				$replacements []	=  isset ( $pdf_objects [ $object_matches [ 'object' ] [$i] ] ) ?
								trim ( $pdf_objects [ $object_matches [ 'object' ] [$i] ] ) : '' ;
			    }

			$object_data	=  str_replace ( $searches, $replacements, $object_data ) ;
		    }


		// To execute faster, run the regular expression only if the object data contains a /Author keyword
		if  ( preg_match_all ( $re, $object_data, $matches, PREG_OFFSET_CAPTURE ) )
		   {
			for  ( $i = 0, $count = count ( $matches [ 'keyword' ] ) ; $i  <  $count ; $i ++ )
			   {
				$keyword	=  $matches [ 'keyword' ] [$i] [0] ;
				$opening	=  $matches [ 'opening' ] [$i] [0] ;
				$start_index	=  $matches [ 'info'    ] [$i] [1] + strlen ( $matches [ 'info' ] [$i] [0] ) ;
				
				// Text between parentheses : the text is written as is
				if  ( $opening  ==  '(' )
				   {
					$parent_level	=  1 ;

					// Since the parameter value can contain any character, including "\" or "(", we will have to find the real closing
					// parenthesis
					$value		=  '' ;

					for  ( $j = $start_index, $object_length = strlen ( $object_data ) ; $j  <  $object_length ; $j ++ )
					   {
						if  ( $object_data [$j]  ==  '\\' )
							$value	.=  '\\' . $object_data [++$j] ;
						else if  ( $object_data [$j]  ==  '(' )
						   {
							$value	.=  '(' ;
							$parent_level ++ ;
						    }
						else if  ( $object_data [$j]  ==  ')' )
						   {
							$parent_level -- ;

							if  ( ! $parent_level )
								break ;
							else
								$value	.=  ')' ;
						    }
						else
							$value  .=  $object_data [$j] ;
					    }
				     }
				// Text within angle brackets, written as hex digits
				else
				   {
					$end_index	=  strpos ( $object_data, '>', $start_index ) ;
					$hexdigits	=  substr ( $object_data, $start_index, $end_index - $start_index ) ;
					$value		=  hex2bin ( str_replace ( array ( "\n", "\r", "\t" ), '', $hexdigits ) ) ;
				    }

				$value		=   $this -> __convert_utf16 ( $this -> __extract_chars_from_block ( $value ) ) ;

				switch ( strtolower ( $keyword ) ) 
				   {
					case  'author'		:  $this -> Author			=  $value ; break ;
					case  'creator'		:  $this -> CreatorApplication		=  $value ; break ;
					case  'producer'	:  $this -> ProducerApplication		=  $value ; break ;
					case  'title'		:  $this -> Title			=  $value ; break ;
					case  'keywords'	:  $this -> Keywords			=  $value ; break ;
					case  'subject'		:  $this -> Subject			=  $value ; break ;
					case  'creationdate'	:  $this -> CreationDate		=  $this -> GetUTCDate ( $value ) ; break ;
					case  'moddate'		:  $this -> ModificationDate		=  $this -> GetUTCDate ( $value ) ; break ;
				    }
			    }

			if  ( self::$DEBUG )
			   {
		   		echo "\n----------------------------------- AUTHOR INFORMATION\n" ;
				echo ( "Author               : " . $this -> Author . "\n" ) ;
				echo ( "Creator application  : " . $this -> CreatorApplication . "\n" ) ;
				echo ( "Producer application : " . $this -> ProducerApplication . "\n" ) ;
				echo ( "Title                : " . $this -> Title . "\n" ) ;
				echo ( "Subject              : " . $this -> Subject . "\n" ) ;
				echo ( "Keywords             : " . $this -> Keywords . "\n" ) ;
				echo ( "Creation date        : " . $this -> CreationDate . "\n" ) ;
				echo ( "Modification date    : " . $this -> ModificationDate . "\n" ) ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        RetrieveFormData - Retrieves raw form data
	
	    PROTOTYPE
	        $this -> RetrieveFormData ( $object_id, $object_data ) ;
	
	    DESCRIPTION
	        Retrieves raw form data (form definition and field values definition).
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the object containing the author information.

		$object_data (string) -
			Object contents.

		$pdf_objects (array) -
			Array whose keys are the PDF object ids, and values their corresponding contents.
		
	    NOTES
	        This function only memorizes the contents of form data definitions. The actual data will be processed
		only if the GetFormData() function is called.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  RetrieveFormData ( $object_id, $object_data, $pdf_objects )
	   {
		// Retrieve the object that contains the field values
		preg_match ( '#\b R \s* \( \s* datasets \s* \) \s* (?P<object> \d+) \s+ \d+ \s+ R#imsx', $object_data, $field_match ) ;
		$field_object		=  $field_match [ 'object' ] ;

		if  ( ! isset ( $pdf_objects [ $field_object ] ) )
		   {
			if  ( self::$DEBUG )
				warning ( "Field definitions object #$field_object not found in object #$object_id." ) ;

			return ;
		    }

		// Retrieve the object that contains the form definition
		preg_match ( '#\b R \s* \( \s* form \s* \) \s* (?P<object> \d+) \s+ \d+ \s+ R#imsx', $object_data, $form_match ) ;
		$form_object		=  $form_match [ 'object' ] ;

		if  ( ! isset ( $pdf_objects [ $form_object ] ) )
		   {
			if  ( self::$DEBUG )
				warning ( "Form definitions object #$form_object not found in object #$object_id." ) ;

			return ;
		    }
		// Add this entry to form data information
		$this -> FormData [ $object_id ]	=  array
		   ( 
			'values'	=>  ( integer ) $field_object,
			'form'		=>  ( integer ) $form_object
		    ) ;
	    }


    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                      FONT TABLE MANAGEMENT                                       ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    PdfTexterFontTable class -
        The PdfTexterFontTable class is not supposed to be used outside the context of the PdfToText class.
	Its purposes are to hold a list of font definitions taken from a pdf document, along with their
	associated character mapping tables, if any.
	This is why no provision has been made to design this class a a general purpose class ; its utility
	exists only in the scope of the PdfToText class.

  ==============================================================================================================*/
class 	PdfTexterFontTable 	extends PdfObjectBase
   {
	// Font table
	public		$Fonts		=  array ( ) ;
	private		$DefaultFont	=  false ;
	// Font mapping between a font number and an object number
	private 	$FontMap 	=  array ( ) ;
	// A character map buffer is used to store results from previous calls to the MapCharacter() method of the 
	// FontTable object. It dramatically reduces the number of calls needed, from one call for each character
	// defined in the pdf stream, to one call on each DISTINCT character defined in the PDF stream.
	// As an example, imagine a PDF file that contains 200K characters, but only 150 distinct ones. The
	// MapCharacter method will be called 150 times, instead of 200 000...
	private		$CharacterMapBuffer		=  array ( ) ;


	// Constructor -
	//	Well, does not do anything special
	public function  __construct ( )
	   {
		parent::__construct ( ) ;
	    }


	// Add -
	//	Adds the current font declaration to the font table. Handles special cases where font id is not
	//	given by the object id, but rather by <</Rx...>> constructs
	public function  Add ( $object_id, $font_definition, $pdf_objects, $extra_mappings )
	   {
		if  ( PdfToText::$DEBUG )
		   {
	   		echo "\n----------------------------------- FONT #$object_id\n" ;
			echo $font_definition ;
		    }

		$font_type		=  PdfTexterFont::FONT_ENCODING_STANDARD ;
		$cmap_id		=  0 ;
		$secondary_cmap_id	=  0 ;
		$font_variant		=  false ;

		// Font resource id specification
	   	if  ( preg_match ( '#<< \s* (?P<rscdefs> /R\d+ .*) >>#ix', $font_definition, $match ) )
		   {
			$resource_definitions	=  $match [ 'rscdefs' ] ;

			preg_match_all ( '#/R (?P<font_id> \d+) #ix', $resource_definitions, $id_matches ) ;
			preg_match_all ( '#/ToUnicode \s* (?P<cmap_id> \d+)#ix', $resource_definitions, $cmap_matches ) ;

			$count		=  count ( $id_matches [ 'font_id' ] ) ;

			for  ( $i = 0 ;  $i  <  $count ; $i ++ )
			   {
				$font_id	=  $id_matches   [ 'font_id' ] [$i] ;
				$cmap_id	=  $cmap_matches [ 'cmap_id' ] [$i] ;

				$this -> Fonts [ $font_id ]	=  new  PdfTexterFont ( $font_id, $cmap_id, PdfTexterFont::FONT_ENCODING_UNICODE_MAP, $extra_mappings ) ;
			    }

			return ;
		    }
		// Experimental implementation of CID fonts
		else if  ( preg_match ( '#/(Base)?Encoding \s* /Identity-H#ix', $font_definition ) )
		   {
			if  ( preg_match ( '#/BaseFont \s* /(?P<font> [^\s/]+)#ix', $font_definition, $match ) )
				$font_variant	=  $match [ 'font' ] ;

			$font_type	=  PdfTexterFont::FONT_ENCODING_CID_IDENTITY_H ;
		    }
		// Font has an associated Unicode map (using the /ToUnicode keyword)
		else if  ( preg_match ( '#/ToUnicode \s* (?P<cmap> \d+)#ix', $font_definition, $match ) )
		   {
			$cmap_id	=  $match [ 'cmap' ] ;
			$font_type	=  PdfTexterFont::FONT_ENCODING_UNICODE_MAP ;

			if  ( preg_match ( '#/Encoding \s* (?P<cmap> \d+)#ix', $font_definition, $secondary_match ) )
				$secondary_cmap_id	=  $secondary_match [ 'cmap' ] ;
		    } 
		// Font has an associated character map (using a cmap id)
		else if  ( preg_match ( '#/Encoding \s* (?P<cmap> \d+) \s+ \d+ #ix', $font_definition, $match ) )
		   {
			$cmap_id 	=  $match [ 'cmap' ] ;
			$font_type	=  PdfTexterFont::FONT_ENCODING_PDF_MAP ;
		    }
		// Font uses the Windows Ansi encoding
		else if  ( preg_match ( '#/(Base)?Encoding \s* /WinAnsiEncoding#ix', $font_definition ) )
		   {
			$font_type	=  PdfTexterFont::FONT_ENCODING_WINANSI ;

			if  ( preg_match ( '# /BaseFont \s* / [a-z0-9_]+ \+ [a-z0-9_]+? Cyr #imsx', $font_definition ) )
				$font_type	|=  PdfTexterFont::FONT_VARIANT_ISO8859_5 ;
		    }
		// Font uses the Mac Roman encoding
		else if  ( preg_match ( '#/(Base)?Encoding \s* /MacRomanEncoding#ix', $font_definition ) )
			$font_type	=  PdfTexterFont::FONT_ENCODING_MAC_ROMAN ;
	
		$this -> Fonts [ $object_id ]	=  new  PdfTexterFont ( $object_id, $cmap_id, $font_type, $secondary_cmap_id, $pdf_objects, $extra_mappings, $font_variant ) ;

		// Arbitrarily set the default font to the first font encountered in the pdf file
		if  ( $this -> DefaultFont  ===  false )
		   {
			reset ( $this -> Fonts ) ;
			$this -> DefaultFont	=  key ( $this -> Fonts ) ;
		    }
	    }


	// AddFontMap -
	//	Process things like :
	//		<</F1 26 0 R/F2 22 0 R/F3 18 0 R>>
	//	which maps font 1 (when specified with the /Fx instruction) to object 26,
	//	2 to object 22 and 3 to object 18, respectively, in the above example.
	//	Found also a strange way of specifying a font mapping :
	//		<</f-0-0 5 0 R etc.
	//	And yet another one :
	//		<</C0_0 5 0 R
	public function  AddFontMap ( $object_id, $object_data )
	   {
		$object_data	=  self::UnescapeHexCharacters ( $object_data ) ;

		// The same object can hold different notations for font associations
		if  ( preg_match_all ( '# (?P<font> ' . self::$FontSpecifiers . ' ) \s+ (?P<object> \d+) #imsx', $object_data, $matches ) )
		   {
			for ( $i = 0, $count = count ( $matches [ 'font' ] ) ; $i  <  $count ; $i ++ )
			   {
				$font	=  $matches [ 'font'   ] [$i] ;
				$object =  $matches [ 'object' ] [$i] ;

				$this -> FontMap [ $font ]	=  $object ;
			    }
		    }
	    }


	// AddPageFontMap -
	//	Adds font aliases to the current font map, in the form : "page:xobject:font".
	//	The associated value is the font object itself.
	public function  AddPageFontMap ( $map )
	   {
		foreach  ( $map  as  $map_entry )
		   {
			$this -> FontMap [ $map_entry [ 'page' ] . ':' . $map_entry [ 'xobject-name' ] . ':' . $map_entry [ 'font-name' ] ]	=  $map_entry [ 'object' ] ;
		    }
	    }


	// AddCharacterMap -
	//	Associates a character map to a font declaration that referenced it.
	public function  AddCharacterMap ( $cmap )
	   {
		$status		=  false ;

		// We loop through all fonts, since the same character map can be referenced by several font definitions
		foreach  ( $this -> Fonts  as  $font )
		   {
			if  ( $font -> CharacterMapId  ==  $cmap -> ObjectId )
			   {
				$font -> CharacterMap	=  $cmap ;
				$status			=  true ;
			    }
			else if  ( $font -> SecondaryCharacterMapId  ==  $cmap -> ObjectId )
			   {
				$cmap -> Secondary		=  true ;
				$font -> SecondaryCharacterMap	=  $cmap ;
				$status				=  true ;
			    }
		    }

		return ( $status ) ;
	    }


	// GetFontAttributes -
	//	Gets the specified font width in hex digits and whether the font has a character map or not.
	public function  GetFontAttributes ( $page_number, $template, $font, &$font_map_width, &$font_mapped ) 
	   {
		// Font considered as global to the document
		if  ( isset ( $this -> Fonts [ $font ] ) )
			$key	=  $font ;
		// Font not found : try to use the first one declared in the document
		else 
		   {
			reset ( $this -> Fonts ) ;
			$key	=  key ( $this -> Fonts ) ;
		    }

		// Font has an associated character map
		if  ( $key  &&  $this -> Fonts [ $key ] -> CharacterMap )
		   {
			$font_map_width		=  $this -> Fonts [ $key ] -> CharacterMap -> HexCharWidth ;
			$font_mapped		=  true ;

			return ( true ) ;
		    }
		// No character map : characters are specified as two hex digits
		else
		   {
			$font_map_width		=  2 ;
			$font_mapped		=  false ;

			return ( false ) ;
		    }
	    }


	// GetFontByMapId -
	//	Returns the font id (object id) associated with the specified mapped id.
	public function  GetFontByMapId ( $page_number, $template, $id )
	   {
		if  ( isset ( $this -> FontMap [ "$page_number:$template:$id" ] ) )
			$font_object	=  $this -> FontMap [ "$page_number:$template:$id" ] ;
		else if  ( isset ( $this -> FontMap [ $id ] ) )
			$font_object	=  $this -> FontMap [ $id ] ;
		else
			$font_object	=  -1 ;

		return ( $font_object ) ;
	    }


	// GetFontObject -
	//	Returns the PdfTexterFont object for the given page, template and font id (in the form of "/something")
	public function  GetFontObject ( $page_number, $template, $id )
	   {
		if  ( isset ( $this -> FontMap [ "$page_number:$template:$id" ] ) )
			$font_object	=  $this -> FontMap [ "$page_number:$template:$id" ] ;
		else if  ( isset ( $this -> FontMap [ $id ] ) )
			$font_object	=  $this -> FontMap [ $id ] ;
		else
			return ( false ) ;

		if  ( isset ( $this -> Fonts [ $font_object ] ) ) 
			return ( $this -> Fonts [ $font_object ] ) ;
		else
			return ( false ) ;
	    }


	// MapCharacter -
	//	Returns the character associated to the specified one.
	public function  MapCharacter ( $font, $ch, $return_false_on_failure = false )
	   {
		if  ( isset ( $this -> CharacterMapBuffer [ $font ] [ $ch ] ) )
			return ( $this -> CharacterMapBuffer [ $font ] [ $ch ] ) ;

		// Use the first declared font as the default font, if none defined
		if  ( $font  ==  -1 )
			$font	=  $this -> DefaultFont ;

		$cache	=  true ;

		if  ( isset  ( $this -> Fonts [ $font ] ) )
		   {
			$font_object	=  $this -> Fonts [ $font ] ;

			$code	=  $font_object -> MapCharacter ( $ch, $return_false_on_failure ) ;

			if  ( $font_object -> CharacterMap )
				$cache	=  $font_object -> CharacterMap -> Cache ;
		    }
		else
		   {
			$code	=  $this -> CodePointToUtf8 ( $ch ) ;
		    }

		if  ( $cache )
			$this -> CharacterMapBuffer [ $font ] [ $ch ]	=  $code ;

		return ( $code ) ;
	    }
    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                         FONT MANAGEMENT                                          ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    PdfTexterFont class -
        The PdfTexterFont class is not supposed to be used outside the context of the PdfToText class.
	It holds an optional character mapping table associted with this font.
	No provision has been made to design this class a a general purpose class ; its utility exists only in
	the scope of the PdfToText class.

  ==============================================================================================================*/
class  PdfTexterFont		extends PdfObjectBase
   {
	// Font encoding types, for fonts that are neither associated with a Unicode character map nor a PDF character map
	const	FONT_ENCODING_STANDARD			=  0 ;			// No character map, use the standard character set
	const	FONT_ENCODING_WINANSI			=  1 ;			// No character map, use the Windows Ansi character set
	const	FONT_ENCODING_MAC_ROMAN			=  2 ;			// No character map, use the MAC OS Roman character set
	const	FONT_ENCODING_UNICODE_MAP		=  3 ;			// Font has an associated unicode character map
	const	FONT_ENCODING_PDF_MAP			=  4 ;			// Font has an associated PDF character map
	const	FONT_ENCODING_CID_IDENTITY_H		=  5 ;			// CID font : IDENTITY-H

	// Font variants
	const   FONT_VARIANT_STANDARD		=  0x0000 ;
	const	FONT_VARIANT_ISO8859_5		=  0x1000 ;		// Cyrillic

	const	FONT_VARIANT_MASK		=  0xF000 ;
	const	FONT_VARIANT_SHIFT		=  12 ;

	// Font resource id (may be an object id, overridden by <</Rx...>> constructs
	public		$Id ;
	// Font type and variant
	public		$FontType ;
	public		$FontVariant ;
	// Character map id, specified by the /ToUnicode flag
	public		$CharacterMapId ;
	// Secondary character map id, specified by the /Encoding flag and that can contain a /Differences flag
	public		$SecondaryCharacterMapId ;
	// Optional character map, that may be set by the PdfToText::Load method just before processing text drawing blocks
	public		$CharacterMap		=  null ;
	public		$SecondaryCharacterMap	=  null ;
	// Character widths
	public		$CharacterWidths	=  array ( ) ;
	// Default character width, if not present in the $CharacterWidths array
	public		$DefaultWidth		=  0 ;
	private		$GotWidthInformation	=  false ;
	// A buffer for remembering character widths
	protected	$CharacterWidthsBuffer	=  array ( ) ;


	// Constructor -
	//	Builds a PdfTexterFont object, using its resource id and optional character map id.
	public function  __construct ( $resource_id, $cmap_id, $font_type, $secondary_cmap_id = null, $pdf_objects = null, $extra_mappings = null, $font_variant = false )
	   {

		parent::__construct ( ) ;

		$this -> Id				=  $resource_id ;
		$this -> CharacterMapId			=  $cmap_id ;
		$this -> SecondaryCharacterMapId	=  $secondary_cmap_id ;
		$this -> FontType			=  $font_type  &  ~self::FONT_VARIANT_MASK ;
		$this -> FontVariant			=  ( $font_type  >>  self::FONT_VARIANT_SHIFT )  &  0x0F ;

		// Instantiate the appropriate character map for this font
		switch  ( $this -> FontType )
		   {
			case	self::FONT_ENCODING_WINANSI :
				$this -> CharacterMap	=  new  PdfTexterAdobeWinAnsiMap ( $resource_id, $this -> FontVariant ) ;
				break ;

			case	self::FONT_ENCODING_MAC_ROMAN :
				$this -> CharacterMap	=  new  PdfTexterAdobeMacRomanMap ( $resource_id, $this -> FontVariant ) ;
				break ;

			case	self::FONT_ENCODING_CID_IDENTITY_H :
				$this -> CharacterMap	=  new PdfTexterIdentityHCIDMap (  $resource_id, $font_variant ) ;
				break ;

			case	self::FONT_ENCODING_PDF_MAP :
				$this -> CharacterMap	=  new  PdfTexterEncodingMap ( $cmap_id, $pdf_objects [ $cmap_id ], $extra_mappings ) ;
				break ;

			case	self::FONT_ENCODING_UNICODE_MAP :
				break ;

			case	self::FONT_ENCODING_STANDARD :
				break ;

			default :
				if  ( PdfToText::$DEBUG )
					warning ( "Unknown font type #$font_type found for object #$resource_id, character map #$cmap_id." ) ;
		    }

		// Get font data ; include font descriptor information if present
		$font_data	=  $pdf_objects [ $resource_id ] ;

		if  ( preg_match ( '/FontDescriptor \s+ (?P<id> \d+) \s+ \d+ \s+ R/imsx', $font_data, $match ) )
		   {
			$descriptor_id		=  $match [ 'id' ] ;

			// Don't care about searching this in that object, or that in this object - simply catenate the font descriptor
			// with the font definition
			if  ( isset ( $pdf_objects [ $descriptor_id ] ) )
				$font_data	.=  $pdf_objects [ $descriptor_id ] ;
		    }	

		// Type1 fonts belong to the Adobe 14 standard fonts available. Information about the character widths is never embedded in the PDF
		// file, but must be taken from external data (in the FontMetrics directory).
		if  ( preg_match ( '#/SubType \s* /Type1#ix', $font_data ) )
		   {
			preg_match ( '#/BaseFont \s* / ([\w]+ \+)? (?P<font> [^\s\[</]+)#ix', $font_data, $match ) ;
			$font_name	=  $match [ 'font' ] ;
			$lc_font_name	=  strtolower ( $font_name ) ;

			// Do that only if a font metrics file exists...
			if  ( isset ( PdfToText::$AdobeStandardFontMetrics [ $lc_font_name ] ) )
			   {
				$metrics_file	=  PdfToText::$FontMetricsDirectory . '/' . PdfToText::$AdobeStandardFontMetrics [ $lc_font_name ] ;

				if  ( file_exists ( $metrics_file ) )
				   {
					include ( $metrics_file ) ;

					if  ( isset ( $charwidths ) )
					   {
						// Build the CharacterWidths table
						foreach  ( $charwidths  as  $char => $width )
							$this -> CharacterWidths [ chr ( $char ) ]	=  ( double ) $width ;

						$this -> GotWidthInformation	=  true ;
					    }
				    }
			    }
	 	    }

		// Retrieve the character widths for this font. This means :
		// - Retrieving the /FirstChar, /LastChar and /Widths entries from the font definition. /Widths is an array of individual character
		//   widths, between the /FirstChar and /LastChar entries. A value of zero in this array means "Use the default width"...
		// - ... which is given by the /MissingWidth parameter, normally put in the font descriptor whose object id is given by the 
		//   /FontDescriptor entry of the font definition
		// Well, to be considered, given the number of buggy PDFs around the world, we won't care about the /LastChar entry and we won't
		// check whether the /Widths array contains (LastChar - FirstChar + 1) integer values...
		// Get the entries 
		$first_char	=  false ;
		$widths		=  false ;
		$missing_width  =  false ;

		if  ( preg_match ( '#/FirstChar \s+ (?P<char> \d+)#imsx', $font_data, $match ) )
			$first_char	=  $match [ 'char' ] ;

		if  ( preg_match ( '#/Widths \s* \[ (?P<widths> [^\]]+) \]#imsx', $font_data, $match ) )
			$widths		=  $match [ 'widths' ] ;

		if  ( preg_match ( '#/MissingWidth \s+ (?P<missing> \d+)#imsx', $font_data, $match ) )
			$missing_width	=  $match [ 'missing' ] ;

		// It would not make sense if one of the two entries /FirstChar and /Widths was missing
		// So ensure they are all there (note that /MissingWidths can be absent)
		if  ( $first_char !==  false  &&  $widths )
		   {
			if  ( $missing_width  !==  false )
				$this -> DefaultWidth		=  ( double ) $missing_width ;

			// Here comes a really tricky part :
			// - The PDF file can contain CharProcs (example names : /a0, /a1, etc.) for which we have no
			//   Unicode equivalent
			// - The caller may have called the AddAdobeExtraMappings method, to providing a mapping between
			//   those char codes (/a0, /a1, etc.) and a Unicode equivalent
			// - Each "charproc" listed in the /Differences array as a specific code, such as :
			//	[0/a1/a2/a3...]
			//   which maps /a1 to code 0, /a2 to code 1, and so on
			// - However, the GetStringWidth() method provides real Unicode characters
			// Consequently, we have to map each CharProc character (/a1, /a2, etc.) to the Unicode value
			// that may have been specified using the AddAdobeExtraMappings() method.
			// The first step below collects the name list of CharProcs.
			$charprocs	=  false ;

			if  ( isset ( $this -> CharacterMap -> Encodings )  &&
				preg_match ( '# /CharProcs \s* << (?P<list> .*?) >>#imsx', $font_data, $match ) )
			   {
				preg_match_all ( '#/ (?P<char> \w+) \s+ \d+ \s+ \d+ \s+ R#msx', $match [ 'list' ], $char_matches ) ;

				$charprocs	=  array_flip ( $char_matches [ 'char' ] ) ;
			    }

			// The /FontMatrix entry defines the scaling to be used for the character widths (among other things)
			if  ( preg_match ( '#/FontMatrix \s* \[ \s* (?P<multiplier> \d+)#imsx', $font_data, $match ) )
				$multiplier	=  1000 * ( double ) $match [ 'multiplier' ] ;
			else
				$multiplier	=  1 ;

			$widths				=  trim ( preg_replace ( '/\s+/', ' ', $widths ) ) ;
			$widths				=  explode ( ' ', $widths ) ;

			for  ( $i = 0, $count = count ( $widths ) ; $i  <  $count ; $i ++ )
			   {
				$value		=  ( double ) trim ( $widths [$i] ) ;
				$chr_index	=  $first_char + $i ;

				// Tricky thing part 2 :
				if  ( $charprocs )
				   {
					// If one of the CharProc characters is listed in the /Differences array then...
					if  ( isset ( $this -> CharacterMap -> DifferencesByPosition [ $chr_index ] ) )
					   {
						$chname		=  $this -> CharacterMap -> DifferencesByPosition [ $chr_index ] ;

						// ... if this CharProcs character is defined in the encoding table (possibly because
						// it was complemeted through a call to the AddAdobeExtraMappings() method), then we
						// will use its Unicode counterpart instead of the character ID coming from the
						// /Differences array)
						if  ( isset ( $charprocs [ $chname ] )  &&  isset ( $this -> CharacterMap -> Encodings [ $chname ] ) )
							$chr_index	=  $this -> CharacterMap -> Encodings [ $chname ] [2] ;
					    }
				    }

				$this -> CharacterWidths [ chr ( $chr_index ) ]		=  ( $value ) ?  ( $value * $multiplier ) : $this -> DefaultWidth ;
			    }

			$this -> GotWidthInformation	=  true ;
		    }
	    }


	// MapCharacter -
	//	Returns the substitution string value for the specified character, if the current font has an
	//	associated character map, or the original character encoded in utf8, if not.
	public function  MapCharacter ( $ch, $return_false_on_failure = false )
	   {
		if  ( $this -> CharacterMap )
		   {
			// Character is defined in the character map ; check if it has been overridden by a /Differences array in
			// a secondary character map
			if  ( isset ( $this -> CharacterMap [ $ch ] ) )
			   {
				// Since a /ToUnicode map can have an associated /Encoding map with a /Differences list, this is the right place
				// to perform the translation (ie, the final Unicode codepoint is impacted by the /Differences list)
				if  ( ! $this -> SecondaryCharacterMap )		// Most common case first !
				   {
					$code	=  $this -> CharacterMap [ $ch ] ;
				    }
				else
				   {
					if  ( isset  ( $this -> SecondaryCharacterMap [ $ch ] ) )
						$code	=  $this -> SecondaryCharacterMap [ $ch ] ;
					else
						$code	=  $this -> CharacterMap [ $ch ] ;
				    }

				return ( $code ) ;
			    }
			// On the contrary, the character may not be defined in the main character map but may exist in the secondary cmap
			else if  ( $this -> SecondaryCharacterMap  &&  isset ( $this -> SecondaryCharacterMap [ $ch ] ) )
			   {
				$code	=  $this -> SecondaryCharacterMap [ $ch ] ;

				return ( $code ) ;
			    }
		    }

		if  ( $return_false_on_failure )
			return ( false ) ;

		return ( $this -> CodePointToUtf8 ( $ch ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetStringWidth - Returns the length of a string, in 1/100 of points
	
	    PROTOTYPE
	        $width		=  $font -> GetStringWidth ( $text, $extra_percent ) ;
	
	    DESCRIPTION
	        Returns the length of a string, in 1/100 of points. 
	
	    PARAMETERS
	        $text (string) -
	                String whose length is to be measured.

		$extra_percent (double) -
			Extra percentage to be added to the computed width.
	
	    RETURN VALUE
	        Returns the length of the specified string in 1/1000 of text points, or 0 if the font does not
		contain any character width information.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetStringWidth ( $text, $extra_percent )
	   {
		// No width information 
		if  ( ! $this -> GotWidthInformation )
			return ( false ) ;

		$width		=  0 ;

		// Compute the width of each individual character - use a character width buffer to avoid
		// repeating the same tests again and again for characters whose width has already been processed
		for  ( $i = 0, $length = strlen ( $text ) ; $i  <  $length ; $i ++ )
		   {
			$ch		=  $text [$i] ;

			// Character already in the Widths buffer - Simply retrieve its value
			if  ( isset ( $this -> CharacterWidthsBuffer [ $ch ] ) )
			   {
				$width	+=  $this -> CharacterWidthsBuffer [ $ch ] ;
			    }
			// New character - The width comes either from the CharacterWidths array if an entry is defined
			// for this character, or from the default width property.
			else
			   {
				if  ( isset ( $this -> CharacterWidths [ $ch ] ) )
				   {
					$width	+=  $this -> CharacterWidths [ $ch ] ;
					$this -> CharacterWidthsBuffer [ $ch ]	=  $this -> CharacterWidths [ $ch ] ;
				    }
				else
				   {
					$width	+=  $this -> DefaultWidth ;
					$this -> CharacterWidthsBuffer [ $ch ]	=  $this -> DefaultWidth ;
				    }
			    }
		    }

		// The computed width is actually longer/smaller than its actual width. Adjust by the percentage specified
		// by the ExtraTextWidth property
		$divisor	=  100 - $extra_percent ;

		if  ( $divisor  <  50 )			// Arbitrarily fix a limit 
			$divisor	=  50 ;

		// All done, return
		return ( $width / $divisor ) ;
	    }
    }


/*==============================================================================================================

    PdfTexterCharacterMap -
        The PdfTexterFont class is not supposed to be used outside the context of the PdfToText class.
	Describes a character map.
	No provision has been made to design this class a a general purpose class ; its utility exists only in
	the scope of the PdfToText class.

  ==============================================================================================================*/
abstract class	PdfTexterCharacterMap	extends		PdfObjectBase
					implements	ArrayAccess, Countable
   {
	// Object id of the character map
	public		$ObjectId ;
	// Number of hex digits in a character represented in hexadecimal notation
	public 		$HexCharWidth ;
	// Set to true if the values returned by the array access operator can safely be cached
	public		$Cache		=  false ;



	public function  __construct ( $object_id )
	   {
		parent::__construct ( ) ;
		$this -> ObjectId	=  $object_id ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	    CreateInstance -
	        Creates a PdfTexterCharacterMap instance of the correct type.

	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  CreateInstance ( $object_id, $definitions, $extra_mappings )
	   {
		if  ( preg_match ( '# (begincmap) | (beginbfchar) | (beginbfrange) #ix', $definitions ) )
			return ( new PdfTexterUnicodeMap ( $object_id, $definitions ) ) ;
		else if  ( stripos ( $definitions, '/Differences' )  !==  false )
			return ( new PdfTexterEncodingMap ( $object_id, $definitions, $extra_mappings ) ) ;
		else
			return ( false ) ;
	    }



	/*--------------------------------------------------------------------------------------------------------------

	        Interface implementations.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextDecodingException ( "Unsupported operation." ) ) ; }

	public function  offsetUnset ( $offset )
	   { error ( new PdfToTextDecodingException ( "Unsupported operation." ) ) ; }
    }



/*==============================================================================================================

    PdfTexterUnicodeMap -
        A class for fonts having a character map specified with the /ToUnicode parameter.

  ==============================================================================================================*/
class  PdfTexterUnicodeMap 	extends 	PdfTexterCharacterMap
    {
	// Id of the character map (specified by the /Rx flag)
	public		$Id	;
	// Character substitution table, using the beginbfrange/endbfrange notation
	// Only constructs of the form :
	//	<low> <high> <start>
	// are stored in this table. Constructs of the form :
	//	<x> <y> [ <subst_x> <subst_x+1> ... <subst_y> ]
	// are stored in the $DirectMap array, because it is conceptually the same thing in the end as a character substitution being
	// defined with the beginbfchar/endbfchar construct.
	// Note that a dichotomic search in $RangeMap will be performed for each character reference not yet seen in the pdf flow.
	// Once the substitution character has been found, it will be added to the $DirectMap array for later faster access.
	// The reason for this optimization is that some pdf files can contain beginbfrange/endbfrange constructs that may seem useless,
	// except for validation purposes (ie, validating the fact that a character reference really belongs to the character map).
	// However, such constructs can lead to thousands of character substitutions ; consider the following example, that comes
	// from a sample I received :
	//	beginbfrange
	//	<1000> <1FFFF> <1000>
	//	<2000> <2FFFF> <2000>
	//	...
	//	<A000> <AFFFF> <A0000>
	//	...
	//	endbfrange
	// By naively storing a one-to-one character relationship in an associative array, such as :
	//	$array [ 0x1000 ] = 0x1000 ;
	//	$array [ 0x1001 ] = 0x1001 ;
	//	..
	//	$array [ 0x1FFF ] = 0x1FFF ;
	//	etc.
	// you may arrive to a situation where the array becomes so big that it exhausts all of the available memory.
	// This is why the ranges are stored as is and a dichotomic search is performed to go faster.
	// Since it is useless to use this method to search the same character twice, when it has been found once, the
	// substitution pair will be put in the $DirectMap array for subsequent accesses (there is little probability that a PDF
	// file contains so much different characters, unless you are processing the whole Unicode table itself ! - but in this
	// case, you will simply have to adjust the value of the memory_limit setting in your php.ini file. Consider that I am
	// not a magician...).
	protected	$RangeMap		=  array ( ) ;
	private		$RangeCount		=  0 ;				// Avoid unnecessary calls to the count() function
	private		$RangeMin		=  PHP_INT_MAX,			// Min and max values of the character ranges
			$RangeMax		=  -1 ;
	// Character substitution table for tables using the beginbfchar notation
	protected	$DirectMap		=  array ( ) ;


	// Constructor -
	//	Analyzes the text contents of a CMAP and extracts mappings from the beginbfchar/endbfchar and
	//	beginbfrange/endbfrange constructs.
	public function  __construct ( $object_id, $definitions )
	   {
		parent::__construct ( $object_id ) ;

		if  ( PdfToText::$DEBUG )
		   {
	   		echo "\n----------------------------------- UNICODE CMAP #$object_id\n" ;
			echo $definitions;
		    }

		// Retrieve the cmap id, if any
		preg_match ( '# /CMapName \s* /R (?P<num> \d+) #ix', $definitions, $match ) ;
		$this -> Id 		=  isset ( $match [ 'num' ] ) ?  $match [ 'num' ] : -1 ;

		// Get the codespace range, which will give us the width of a character specified in hexadecimal notation
		preg_match ( '# begincodespacerange \s+ <\s* (?P<low> [0-9a-f]+) \s*> \s* <\s* (?P<high> [0-9a-f]+) \s*> \s*endcodespacerange #ix', $definitions, $match ) ;

		if  ( isset ( $match [ 'low' ] ) )
			$this -> HexCharWidth 	=  max ( strlen ( $match [ 'low' ] ), strlen ( $match [ 'high' ] ) ) ;
		else
			$this -> HexCharWidth	=  0 ;

		$max_found_char_width	=  0 ;

		// Process beginbfchar/endbfchar constructs
		if  ( preg_match_all ( '/ beginbfchar \s* (?P<chars> .*?) endbfchar /imsx', $definitions, $char_matches ) )
		    {
		    	foreach  ( $char_matches [ 'chars' ]  as  $char_list )
		    	   {
				// beginbfchar / endbfchar constructs can behave as a kind of beginfbfrange/endbfrange ; example :
				//	<21> <0009 0020 000d>
				// means :
				//	. Map character #21 to #0009
				//	. Map character #22 to #0020
				//	. Map character #23 to #000D
				// There is no clue in the Adobe PDF specification that a single character could be mapped to a range.
				// The normal constructs would be :
				//	<21> <0009>
				//	<22> <0020>
				//	<23> <0000D>
				preg_match_all ( '/< \s* (?P<item> .*?) \s* >/msx', $char_list, $item_matches ) ;
	
				for  ( $i = 0, $item_count = count ( $item_matches [ 'item' ] ) ; $i  <  $item_count ; $i += 2 )
				   {
					$char		=  hexdec ( $item_matches [ 'item' ] [$i] ) ;
					$char_width	=  strlen ( $item_matches [ 'item' ] [$i] ) ;
					$map		=  explode ( ' ', preg_replace ( '/\s+/', ' ', $item_matches [ 'item' ] [ $i + 1 ] ) ) ;

					if  ( $char_width  >  $max_found_char_width )
						$max_found_char_width	=  $char_width ;

					for  ( $j = 0, $map_count = count ( $map ) ; $j  <  $map_count ; $j ++ )
					   {
						$subst				=  hexdec ( $map [$j] ) ;

						// Check for this very special, not really document feature which maps CIDs to a non-existing Unicode character
						// (but it still corresponds to something...)
						if  ( isset ( PdfTexterAdobeUndocumentedUnicodeMap::$UnicodeMap [ $subst ] ) )
							$subst	=  PdfTexterAdobeUndocumentedUnicodeMap::$UnicodeMap [ $subst ] ;

						$this -> DirectMap [ $char + $j ]	=  $subst ;
					    }
				    }

		    	    }
		     }

		// Process beginbfrange/endbfrange constructs
		if  ( preg_match_all ( '/ beginbfrange \s* (?P<ranges> .*?) endbfrange /imsx', $definitions, $range_matches ) )
		   {
			foreach  ( $range_matches [ 'ranges' ]  as  $range_list )
			   {
				$start_index	=  0 ;

				// There are two forms of syntax in a beginbfrange..endbfrange construct
				// 1) "<x> <y> <z>", which maps character ids x through y to z through (z+y-x)
				// 2) "<x> <y> [<a1> <a2> ... <an>]", which maps character x to a1, x+1 to a2, up to y, which is mapped to an
				// All the values are hex digits.
				// We will loop through the range definitions by first identifying the <x> and <y>, and the character that follows
				// them, which is either a "<" for notation 1), or a "[" for notation 2).
				while  ( preg_match ( '#  < \s* (?P<from> [0-9a-f]+) \s* > \s* < \s* (?P<to> [0-9a-f]+) \s* > \s* (?P<nextchar> .) #imsx',
						$range_list, $range_match, PREG_OFFSET_CAPTURE, $start_index ) )
				   {
					$from			=  hexdec ( $range_match [ 'from' ] [0] ) ;
					$to			=  hexdec ( $range_match [ 'to'   ] [0] ) ;
					$next_char		=  $range_match [ 'nextchar' ] [0] ;
					$next_char_index	=  $range_match [ 'nextchar' ] [1] ;
					$char_width		=  strlen ( $range_match [ 'from' ] [0] ) ;

					if  ( $char_width  >  $max_found_char_width )
						$max_found_char_width	=  $char_width ;

					// Form 1) : catch the third hex value after <x> and <y>
					if  ( $next_char  ==  '<' )
					   {
						if  ( preg_match ( '/ \s* (?P<start> [0-9a-f]+) (?P<tail> \s* > \s*) /imsx', $range_list, $start_match, PREG_OFFSET_CAPTURE, $next_char_index + 1 ) )
						   {
							$subst		=  hexdec ( $start_match [ 'start' ] [0] ) ;

							// Check for this very special, not really document feature which maps CIDs to a non-existing Unicode character
							// (but it still corresponds to something...)
							if  ( isset ( PdfTexterAdobeUndocumentedUnicodeMap::$UnicodeMap [ $subst ] ) )
								$subst	=  PdfTexterAdobeUndocumentedUnicodeMap::$UnicodeMap [ $subst ] ;

							// Don't create a range if <x> and <y> are the same
							if  ( $from  !=  $to )
							   {
								$this -> RangeMap []	=  array ( $from, $to, $subst ) ;

								// Adjust min and max values for the ranges stored in this character map - to avoid unnecessary testing
								if  ( $from  <  $this -> RangeMin )
									$this -> RangeMin	=  $from ;

								if  ( $to  >  $this -> RangeMax )
									$this -> RangeMax	=  $to ;
							    }
							else
								$this -> DirectMap [ $from ]	=  $subst ;
							
							$start_index	=  $start_match [ 'tail' ] [1] + 1 ;
						    }
						else
							error ( "Character range $from..$to not followed by an hexadecimal value in Unicode map #$object_id." ) ;
					    }
					// Form 2) : catch all the hex values between square brackets after <x> and <y>
					else if  ( $next_char  ==  '[' )
					   {
						if  ( preg_match ( '/ (?P<values> [\s<>0-9a-f]+ ) (?P<tail> \] \s*)/imsx', $range_list, $array_match, PREG_OFFSET_CAPTURE, $next_char_index + 1 ) )
						   {
							preg_match_all ( '/ < \s* (?P<num> [0-9a-f]+) \s* > /imsx', $array_match [ 'values' ] [0], $array_values ) ;

							for  ( $i = $from, $count = 0 ; $i  <=  $to ; $i ++, $count ++ )
								$this -> DirectMap [$i] 	=  hexdec ( $array_values [ 'num' ] [ $count ] ) ;

							$start_index	=  $array_match [ 'tail' ] [1] + 1 ;
						    }
						else
							error ( "Character range $from..$to not followed by an array of hexadecimal values in Unicode map #$object_id." ) ;
					    }
					else
					   {
						error ( "Unexpected character '$next_char' in Unicode map #$object_id." ) ;
						$start_index	=  $range_match [ 'nextchar' ] [1] + 1 ;
					    }
				    }
			    }

			// Sort the ranges by their starting offsets 
			$this -> RangeCount	=  count ( $this -> RangeMap ) ;

			if  ( $this -> RangeCount  >  1 )
			   {
				usort ( $this -> RangeMap, array ( $this, '__rangemap_cmpfunc' ) ) ;
			    }
		    }

		if ( $max_found_char_width  &&  $max_found_char_width  !=  $this -> HexCharWidth )
		   {
			if  ( PdfToText::$DEBUG )
				warning ( "Character map #$object_id : specified code width ({$this -> HexCharWidth}) differs from actual width ($max_found_char_width)." ) ;

			$this -> HexCharWidth	=  $max_found_char_width ;
		    }
	     }


	public function  __rangemap_cmpfunc ( $a, $b )
	   { return ( $a [0] - $b [0] ) ; }


	/*--------------------------------------------------------------------------------------------------------------

	        Interface implementations.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( count ( $this -> DirectMap ) ) ; }


	public function  offsetExists ( $offset )
	   { return  ( $this -> offsetGetSafe ( $offset )  !==  false ) ; }


	public function  offsetGetSafe ( $offset, $translate = true )
	   {
		// Return value
		$code	=  false ;

		// Character already has an entry (character reference => subtituted character)
		if  ( isset ( $this -> DirectMap [ $offset ] ) )
		   {
			$code	=  ( $translate ) ? $this -> CodePointToUtf8 ( $this -> DirectMap [ $offset ] ) : $this -> DirectMap [ $offset ] ;
		    }
		// Character does not has a direct entry ; have a look in the character ranges defined for this map
		else if  ( $this -> RangeCount  &&  $offset  >=  $this -> RangeMin  &&  $offset  <=  $this -> RangeMax )
		   {
			$low		=  0 ;
			$high		=  count ( $this -> RangeMap ) - 1 ;
			$result		=  false ;

			// Use a dichotomic search through character ranges
			while  ( $low  <=  $high )
			   {
				$middle		=  ( $low + $high )  >>  1 ;

				if  ( $offset  <  $this -> RangeMap [ $middle ] [0] )
					$high	=  $middle - 1 ;
				else if  ( $offset  >  $this -> RangeMap [ $middle ] [1] )
					$low	=  $middle + 1 ;
				else
				   {
					$result	=  $this -> RangeMap [ $middle ] [2] + $offset - $this -> RangeMap [ $middle ] [0] ;
					break ;
				    }
			    }

			// Once a character has been found in the ranges defined by this character map, store it in the DirectMap property
			// so that it will be directly retrieved during subsequent accesses
			if  ( $result  !==  false )
			   {
				$code				=  ( $translate ) ? $this -> CodePointToUtf8 ( $result ) : $result ;
				$this -> DirectMap [ $offset ]	=  $result ;
			    }
		    }

		// All done, return
		return ( $code ) ;
	    }


	public function  offsetGet ( $offset )
	   {
		$code	=  $this -> offsetGetSafe ( $offset ) ;

		if  ( $code  === false )
			$code	=  $this -> CodePointToUtf8 ( $offset ) ;

		return ( $code ) ;
	    }
    }


/*==============================================================================================================

    PdfTexterEncodingMap -
        A class for fonts having a character map specified with the /Encoding parameter.

  ==============================================================================================================*/
class  PdfTexterEncodingMap 	extends  PdfTexterCharacterMap
   {
	// Possible encodings (there is a 5th one, MacExpertEncoding, but used for "expert fonts" ; no need to deal
	// with it here since we only want to extract text)
	// Note that the values of these constants are direct indices to the second dimension of the $Encodings table
	const 	PDF_STANDARD_ENCODING 		=  0 ;
	const 	PDF_MAC_ROMAN_ENCODING 		=  1 ;
	const 	PDF_WIN_ANSI_ENCODING 		=  2 ;
	const 	PDF_DOC_ENCODING 		=  3 ;

	// Correspondance between an encoding name and its corresponding character in the
	// following format : Standard, Mac, Windows, Pdf
	private static 		$GlobalEncodings 	=  false ;
	public			$Encodings ;
	// Encoding type (one of the PDF_*_ENCODING constants)
	public 			$Encoding ;
	// Indicates whether this character map is a secondary one used for Unicode maps ; this must be set at
	// a higher level by the PdfTexterFont because at the time a character map is instantiated, we do not know
	// yet whether it will be a primary (normal) map, or a map secondary to an existing Unicode map
	public			$Secondary ;
	// Differences array (a character substitution table to the standard encodings)
	public 			$Map 			=  array ( ) ;
	// A secondary map for the Differences array, which only contains the differences ; this is used
	// for Unicode fonts that also have an associated /Differences parameter, which should not include the
	// whole standard Adobe character map but only the differences of encodings
	public			$SecondaryMap		=  array ( ) ;
	// Differences by position number
	public			$DifferencesByPosition	=  array ( ) ;


   	// Constructor -
	//	Analyzes the text contents of a CMAP and extracts mappings from the beginbfchar/endbfchar and
	//	beginbfrange/endbfrange constructs.
	public function  __construct ( $object_id, $definitions, $extra_mappings )
	   {
		// Ignore character variants whose names end with these suffixes
		static	$IgnoredVariants	=  array
		   (
			'/\.scalt$/', 
			'/\.sc$/', 
			'/\.fitted$/', 
			'/\.oldstyle$/', 
			'/\.taboldstyle$/', 
			'/\.alt$/',
			'/alt$/',
		    ) ;

		parent::__construct ( $object_id ) ;

		// Load the default Adobe character sets, if not already done
		if  ( self::$GlobalEncodings  ===  false )
		   {
			$charset_file		=  dirname ( __FILE__ ) .  '/Maps/adobe-charsets.map' ;
			include ( $charset_file ) ;
			self::$GlobalEncodings	=  ( isset ( $adobe_charsets ) ) ?  $adobe_charsets : array ( ) ;
		    }

		$this -> Encodings		=  array_merge ( self::$GlobalEncodings, $extra_mappings ) ;

		// Fonts using default Adobe character sets and hexadecimal representations are one-byte long
		$this -> HexCharWidth	=  2 ;

		if  ( PdfToText::$DEBUG )
		   {
	   		echo "\n----------------------------------- ENCODING CMAP #$object_id\n" ;
			echo $definitions;
		    }

		// Retrieve text encoding
		preg_match ( '# / (?P<encoding> (WinAnsiEncoding) | (PDFDocEncoding) | (MacRomanEncoding) | (StandardEncoding) ) #ix',
				$definitions, $encoding_match ) ;

		if ( ! isset ( $encoding_match [ 'encoding' ] ) )
			$encoding_match [ 'encoding' ]	=  'WinAnsiEncoding' ;

		switch ( strtolower ( $encoding_match [ 'encoding' ] ) )
		   {
		   	case 	'pdfdocencoding' 	:  $this -> Encoding	=  self::PDF_DOC_ENCODING 	; break ;
		   	case 	'macromanencoding' 	:  $this -> Encoding 	=  self::PDF_MAC_ROMAN_ENCODING ; break ;
		   	case 	'standardencoding' 	:  $this -> Encoding 	=  self::PDF_STANDARD_ENCODING 	; break ;
		   	case 	'winansiencoding' 	:
		   	default 		 	:  $this -> Encoding 	=  self::PDF_WIN_ANSI_ENCODING	;
		    }

		// Build a virgin character map using the detected encoding
		foreach  ( $this -> Encodings  as  $code_array )
		   {
			$char 			=  $code_array [ $this -> Encoding ] ;
			$this -> Map [ $char ] 	=  $char ;
		    }

		// Extract the Differences array
	   	preg_match ( '/ \[ \s* (?P<contents> [^\]]*?)  \s* \] /x', $definitions, $match ) ;

		if (  ! isset ( $match [ 'contents' ] ) ) 
			return ;

		$data 		=  trim ( preg_replace ( '/\s+(\d+)/', '/$1', $match [ 'contents' ] ) ) ;
		$items 		=  explode ( '/', $data ) ;
		$index		=  0 ;

		for  ( $i = 0, $item_count = count ( $items ) ; $i  <  $item_count ; $i ++ )
		   {
		   	$item 		=  PdfToText::DecodeRawName ( trim ( $items [$i] ) ) ;

		   	// Integer value  : index of next character in map
			if  ( is_numeric ( $item ) )
				$index 	=  ( integer ) $item ;
			// String value : a character name, as defined by Adobe
			else
			   {
				// Remove variant part of the character name
				$item	=  preg_replace  ( $IgnoredVariants, '', trim ( $item ) ) ;

			   	// Keyword (character name) exists in the encoding table
				if  ( isset ( $this -> Encodings [ $item ] ) )
				   {
					$this -> Map [ $index ] 		=  
					$this -> SecondaryMap [ $index ]	=  $this -> Encodings [ $item ] [ $this -> Encoding ] ;
				    }
				// Not defined ; check if this is the "/gxx" notation, where "xx" is a number
				else if  ( preg_match ( '/g (?P<value> \d+)/x', $item, $match ) )
				   {
					$value		=  ( integer ) $match [ 'value' ] ;

					// In my current state of investigations, the /g notation has the following characteristics :
					// - The value 29 must be added to the number after the "/g" string (why ???)
					// - The value after the "/g" string can be greater than 255, meaning that it could be Unicode codepoint
					// This has to be carefully watched before revision
					$value	+=  29 ;

					$this -> Map [ $index ]			=  
					$this -> SecondaryMap [ $index ]	=  $value ;
				    }
				// Some characters can be specified by the "/uni" prefix followed by a sequence of hex digits,
				// which is not described by the PDF specifications. This sequence gives a Unicode code point.
				else if  ( preg_match ( '/uni (?P<value>  [0-9a-f]+)/ix', $item, $match ) )
				   {
					$value		=  hexdec ( $match [ 'value' ] ) ;

					$this -> Map [ $index ]			=  
					$this -> SecondaryMap [ $index ]	=  ( integer ) $value ;
				    }
				// Otherwise, put a quotation mark instead
				else
				   {
					if  ( PdfToText::$DEBUG )
						 warning ( "Unknown character name found in a /Differences[] array : [$item]" ) ;

					$this -> Map [ $index ] 		=  
					$this -> SecondaryMap [ $index ]	=  ord ( '?' ) ;
				    }

				$this -> DifferencesByPosition [ $index ]	=  $item ;

				$index ++ ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------

	        Interface implementations.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( count ( $this -> Map ) ) ; }


	public function  offsetExists ( $offset )
	   { 
		return ( ( ! $this -> Secondary ) ?  
				isset ( $this -> Map [ $offset ] ) :
				isset ( $this -> SecondaryMap [ $offset ] ) ) ; 
	    }


	public function  offsetGet ( $offset )
	   {
		if  ( ! $this -> Secondary )
		   {
			if  ( isset ( $this -> Map [ $offset ] ) )
				$ord		=  $this -> Map [ $offset ] ;
			else
				$ord		=  $offset ;

			// Check for final character translations (concerns only a few number of characters)
			if  ( $this -> Encoding  ==  self::PDF_WIN_ANSI_ENCODING  &&  isset ( PdfTexterAdobeWinAnsiMap::$WinAnsiCharacterMap [0] [ $ord ] ) )
				$ord	=  PdfTexterAdobeWinAnsiMap::$WinAnsiCharacterMap [0] [ $ord ] ;
			else if  ( $this -> Encoding  ==  self::PDF_MAC_ROMAN_ENCODING  &&  isset ( PdfTexterAdobeMacRomanMap::$MacRomanCharacterMap [0] [ $ord ] ) )
				$ord	=  PdfTexterAdobeMacRomanMap::$MacRomanCharacterMap [0] [ $ord ] ;
			// As far as I have been able to see, the values expressed by the /Differences tag were the only ones used within the
			// Pdf document ; however, handle the case where some characters do not belong to the characters listed by /Differences,
			// and use the official Adobe encoding maps when necessary
			else if  ( isset ( $this -> Encodings [ $ord ] [ $this -> Encoding ] ) )
				$ord	=  $this -> Encodings [ $ord ] [ $this -> Encoding ] ;
		
			$result		=  $this -> CodePointToUtf8 ( $ord ) ;
		    }
		else if  ( isset ( $this -> SecondaryMap [ $offset ] ) )
		   {
			$ord		=   $this -> SecondaryMap [ $offset ] ;
			$result		=   $this -> CodePointToUtf8 ( $ord ) ;
		    }
		else
			$result		=  false ;

		return ( $result ) ;
	    }
    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                     CHARACTER MAP MANAGEMENT                                     ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    class PdfTexterAdobeMap -
        Abstract class to handle Adobe-specific fonts.

  ==============================================================================================================*/
abstract class  PdfTexterAdobeMap	extends  PdfTexterCharacterMap
   {
	// Font variant ; one of the PdfTexterFont::FONT_VARIANT_* constants
	public		$Variant ;
	// To be declared by derived classes :
	public		$Map ;

	
	public function  __construct ( $object_id, $font_variant, $map )
	   {
		parent::__construct ( $object_id ) ;

		$this -> HexCharWidth	=  2 ;
		$this -> Variant	=  $font_variant ;
		$this -> Map		=  $map ;

		if  ( ! isset ( $map [ $font_variant ] ) )
			error ( new  PdfToTextDecodingException ( "Undefined font variant #$font_variant." ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	        Interface implementations.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( count ( $this -> $Map [ $this -> Variant ] ) ) ; }


	public function  offsetExists ( $offset )
	   { return ( isset ( $this -> Map [ $this-> Variant ] [ $offset ] ) ) ; }


	public function  offsetGet ( $offset )
	   {
		if  ( isset ( $this -> Map [ $this-> Variant ] [ $offset ] ) )
			$ord		=  $this -> Map [ $this -> Variant ] [ $offset ] ;
		else
			$ord		=  $offset ;
		
		return ( $this -> CodePointToUtf8 ( $ord ) ) ;
	    }
    }


/*==============================================================================================================

    class PdfTexterAdobeWinAnsiMap -
        Abstract class to handle Adobe-specific Win Ansi fonts.

  ==============================================================================================================*/
class	PdfTexterAdobeWinAnsiMap		extends		PdfTexterAdobeMap
   {
	// Windows Ansi mapping to Unicode. Only substitutions that have no direct equivalent are listed here
	// Source : https://msdn.microsoft.com/en-us/goglobal/cc305145.aspx
	// Only characters from 0x80 to 0x9F have no direct translation
	public static	$WinAnsiCharacterMap	=  array
	   (
		// Normal WinAnsi mapping
		0	=>  array 
		   (
			0x80	=>  0x20AC,
			0x82	=>  0x201A,
			0x83	=>  0x0192,
			0x84	=>  0x201E,
			0x85	=>  0x2026,
			0x86	=>  0x2020,
			0x87	=>  0x2021,
			0x88	=>  0x02C6,
			0x89	=>  0x2030,
			0x8A	=>  0x0160,
			0x8B	=>  0x2039,
			0x8C	=>  0x0152,
			0x8E	=>  0x017D,
			0x91	=>  0x2018,
			0x92	=>  0x2019,
			0x93	=>  0x201C,
			0x94	=>  0x201D,
			0x95	=>  0x2022,
			0x96	=>  0x2013,
			0x97	=>  0x2014,
			0x98	=>  0x02DC,
			0x99	=>  0x2122,
			0x9A	=>  0x0161,
			0x9B	=>  0x203A,
			0x9C	=>  0x0153,
			0x9E	=>  0x017E,
			0x9F	=>  0x0178
		     ), 
		// Cyrillic (IS08859-5)
		1	=>  array 
		   (
			0x93	=> 0x0022,	// Quotes
			0x94	=> 0x0022,
			0xC0	=> 0x0410,
			0xC1	=> 0x0411,
			0xC2	=> 0x0412,
			0xC3	=> 0x0413,
			0xC4	=> 0x0414,
			0xC5	=> 0x0415,
			0xC6	=> 0x0416,
			0xC7	=> 0x0417,
			0xC8	=> 0x0418,
			0xC9	=> 0x0419,
			0xCA	=> 0x041A,
			0xCB	=> 0x041B,
			0xCC	=> 0x041C,
			0xCD	=> 0x041D,
			0xCE	=> 0x041E,
			0xCF	=> 0x041F,
			0xD0	=> 0x0420,
			0xD1	=> 0x0421,
			0xD2	=> 0x0422,
			0xD3	=> 0x0423,
			0xD4	=> 0x0424,
			0xD5	=> 0x0425,
			0xD6	=> 0x0426,
			0xD7	=> 0x0427,
			0xD8	=> 0x0428,
			0xD9	=> 0x0429,
			0xDA	=> 0x042A,
			0xDB	=> 0x042B,
			0xDC	=> 0x042C,
			0xDD	=> 0x042D,
			0xDE	=> 0x042E,
			0xDF	=> 0x042F,
			0xE0	=> 0x0430,
			0xE1	=> 0x0431,
			0xE2	=> 0x0432,
			0xE3	=> 0x0433,
			0xE4	=> 0x0434,
			0xE5	=> 0x0435,
			0xE6	=> 0x0436,
			0xE7	=> 0x0437,
			0xE8	=> 0x0438,
			0xE9	=> 0x0439,
			0xEA	=> 0x043A,
			0xEB	=> 0x043B,
			0xEC	=> 0x043C,
			0xED	=> 0x043D,
			0xEE	=> 0x043E,
			0xEF	=> 0x043F,
			0xF0	=> 0x0440,
			0xF1	=> 0x0441,
			0xF2	=> 0x0442,
			0xF3	=> 0x0443,
			0xF4	=> 0x0444,
			0xF5	=> 0x0445,
			0xF6	=> 0x0446,
			0xF7	=> 0x0447,
			0xF8	=> 0x0448,
			0xF9	=> 0x0449,
			0xFA	=> 0x044A,
			0xFB	=> 0x044B,
			0xFC	=> 0x044C,
			0xFD	=> 0x044D,
			0xFE	=> 0x044E,
			0xFF	=> 0x044F
		    )
	    ) ;

	public function  __construct ( $object_id, $font_variant )
	   {
		parent::__construct ( $object_id, $font_variant, self::$WinAnsiCharacterMap ) ;
	    }
    }


/*==============================================================================================================

    class PdfTexterAdobeMacRomanMap -
        Abstract class to handle Adobe-specific Mac Roman fonts.

  ==============================================================================================================*/
class	PdfTexterAdobeMacRomanMap		extends		PdfTexterAdobeMap
   {
	// Mac roman to Unicode encoding
	// Source : ftp://ftp.unicode.org/Public/MAPPINGS/VENDORS/APPLE/ROMAN.TXT
	public static	$MacRomanCharacterMap	=  array
	   (
		0	=>  array
		   (
			0x80	=>  0x00C4,	# LATIN CAPITAL LETTER A WITH DIAERESIS
			0x81	=>  0x00C5,	# LATIN CAPITAL LETTER A WITH RING ABOVE
			0x82	=>  0x00C7,	# LATIN CAPITAL LETTER C WITH CEDILLA
			0x83	=>  0x00C9,	# LATIN CAPITAL LETTER E WITH ACUTE
			0x84	=>  0x00D1,	# LATIN CAPITAL LETTER N WITH TILDE
			0x85	=>  0x00D6,	# LATIN CAPITAL LETTER O WITH DIAERESIS
			0x86	=>  0x00DC,	# LATIN CAPITAL LETTER U WITH DIAERESIS
			0x87	=>  0x00E1,	# LATIN SMALL LETTER A WITH ACUTE
			0x88	=>  0x00E0,	# LATIN SMALL LETTER A WITH GRAVE
			0x89	=>  0x00E2,	# LATIN SMALL LETTER A WITH CIRCUMFLEX
			0x8A	=>  0x00E4,	# LATIN SMALL LETTER A WITH DIAERESIS
			0x8B	=>  0x00E3,	# LATIN SMALL LETTER A WITH TILDE
			0x8C	=>  0x00E5,	# LATIN SMALL LETTER A WITH RING ABOVE
			0x8D	=>  0x00E7,	# LATIN SMALL LETTER C WITH CEDILLA
			0x8E	=>  0x00E9,	# LATIN SMALL LETTER E WITH ACUTE
			0x8F	=>  0x00E8,	# LATIN SMALL LETTER E WITH GRAVE
			0x90	=>  0x00EA,	# LATIN SMALL LETTER E WITH CIRCUMFLEX
			0x91	=>  0x00EB,	# LATIN SMALL LETTER E WITH DIAERESIS
			0x92	=>  0x00ED,	# LATIN SMALL LETTER I WITH ACUTE
			0x93	=>  0x00EC,	# LATIN SMALL LETTER I WITH GRAVE
			0x94	=>  0x00EE,	# LATIN SMALL LETTER I WITH CIRCUMFLEX
			0x95	=>  0x00EF,	# LATIN SMALL LETTER I WITH DIAERESIS
			0x96	=>  0x00F1,	# LATIN SMALL LETTER N WITH TILDE
			0x97	=>  0x00F3,	# LATIN SMALL LETTER O WITH ACUTE
			0x98	=>  0x00F2,	# LATIN SMALL LETTER O WITH GRAVE
			0x99	=>  0x00F4,	# LATIN SMALL LETTER O WITH CIRCUMFLEX
			0x9A	=>  0x00F6,	# LATIN SMALL LETTER O WITH DIAERESIS
			0x9B	=>  0x00F5,	# LATIN SMALL LETTER O WITH TILDE
			0x9C	=>  0x00FA,	# LATIN SMALL LETTER U WITH ACUTE
			0x9D	=>  0x00F9,	# LATIN SMALL LETTER U WITH GRAVE
			0x9E	=>  0x00FB,	# LATIN SMALL LETTER U WITH CIRCUMFLEX
			0x9F	=>  0x00FC,	# LATIN SMALL LETTER U WITH DIAERESIS
			0xA0	=>  0x2020,	# DAGGER
			0xA1	=>  0x00B0,	# DEGREE SIGN
			0xA2	=>  0x00A2,	# CENT SIGN
			0xA3	=>  0x00A3,	# POUND SIGN
			0xA4	=>  0x00A7,	# SECTION SIGN
			0xA5	=>  0x2022,	# BULLET
			0xA6	=>  0x00B6,	# PILCROW SIGN
			0xA7	=>  0x00DF,	# LATIN SMALL LETTER SHARP S
			0xA8	=>  0x00AE,	# REGISTERED SIGN
			0xA9	=>  0x00A9,	# COPYRIGHT SIGN
			0xAA	=>  0x2122,	# TRADE MARK SIGN
			0xAB	=>  0x00B4,	# ACUTE ACCENT
			0xAC	=>  0x00A8,	# DIAERESIS
			0xAD	=>  0x2260,	# NOT EQUAL TO
			0xAE	=>  0x00C6,	# LATIN CAPITAL LETTER AE
			0xAF	=>  0x00D8,	# LATIN CAPITAL LETTER O WITH STROKE
			0xB0	=>  0x221E,	# INFINITY
			0xB1	=>  0x00B1,	# PLUS-MINUS SIGN
			0xB2	=>  0x2264,	# LESS-THAN OR EQUAL TO
			0xB3	=>  0x2265,	# GREATER-THAN OR EQUAL TO
			0xB4	=>  0x00A5,	# YEN SIGN
			0xB5	=>  0x00B5,	# MICRO SIGN
			0xB6	=>  0x2202,	# PARTIAL DIFFERENTIAL
			0xB7	=>  0x2211,	# N-ARY SUMMATION
			0xB8	=>  0x220F,	# N-ARY PRODUCT
			0xB9	=>  0x03C0,	# GREEK SMALL LETTER PI
			0xBA	=>  0x222B,	# INTEGRAL
			0xBB	=>  0x00AA,	# FEMININE ORDINAL INDICATOR
			0xBC	=>  0x00BA,	# MASCULINE ORDINAL INDICATOR
			0xBD	=>  0x03A9,	# GREEK CAPITAL LETTER OMEGA
			0xBE	=>  0x00E6,	# LATIN SMALL LETTER AE
			0xBF	=>  0x00F8,	# LATIN SMALL LETTER O WITH STROKE
			0xC0	=>  0x00BF,	# INVERTED QUESTION MARK
			0xC1	=>  0x00A1,	# INVERTED EXCLAMATION MARK
			0xC2	=>  0x00AC,	# NOT SIGN
			0xC3	=>  0x221A,	# SQUARE ROOT
			0xC4	=>  0x0192,	# LATIN SMALL LETTER F WITH HOOK
			0xC5	=>  0x2248,	# ALMOST EQUAL TO
			0xC6	=>  0x2206,	# INCREMENT
			0xC7	=>  0x00AB,	# LEFT-POINTING DOUBLE ANGLE QUOTATION MARK
			0xC8	=>  0x00BB,	# RIGHT-POINTING DOUBLE ANGLE QUOTATION MARK
			0xC9	=>  0x2026,	# HORIZONTAL ELLIPSIS
			0xCA	=>  0x00A0,	# NO-BREAK SPACE
			0xCB	=>  0x00C0,	# LATIN CAPITAL LETTER A WITH GRAVE
			0xCC	=>  0x00C3,	# LATIN CAPITAL LETTER A WITH TILDE
			0xCD	=>  0x00D5,	# LATIN CAPITAL LETTER O WITH TILDE
			0xCE	=>  0x0152,	# LATIN CAPITAL LIGATURE OE
			0xCF	=>  0x0153,	# LATIN SMALL LIGATURE OE
			0xD0	=>  0x2013,	# EN DASH
			0xD1	=>  0x2014,	# EM DASH
			0xD2	=>  0x201C,	# LEFT DOUBLE QUOTATION MARK
			0xD3	=>  0x201D,	# RIGHT DOUBLE QUOTATION MARK
			0xD4	=>  0x2018,	# LEFT SINGLE QUOTATION MARK
			0xD5	=>  0x2019,	# RIGHT SINGLE QUOTATION MARK
			0xD6	=>  0x00F7,	# DIVISION SIGN
			0xD7	=>  0x25CA,	# LOZENGE
			0xD8	=>  0x00FF,	# LATIN SMALL LETTER Y WITH DIAERESIS
			0xD9	=>  0x0178,	# LATIN CAPITAL LETTER Y WITH DIAERESIS
			0xDA	=>  0x2044,	# FRACTION SLASH
			0xDB	=>  0x20AC,	# EURO SIGN
			0xDC	=>  0x2039,	# SINGLE LEFT-POINTING ANGLE QUOTATION MARK
			0xDD	=>  0x203A,	# SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
			0xDE	=>  0xFB01,	# LATIN SMALL LIGATURE FI
			0xDF	=>  0xFB02,	# LATIN SMALL LIGATURE FL
			0xE0	=>  0x2021,	# DOUBLE DAGGER
			0xE1	=>  0x00B7,	# MIDDLE DOT
			0xE2	=>  0x201A,	# SINGLE LOW-9 QUOTATION MARK
			0xE3	=>  0x201E,	# DOUBLE LOW-9 QUOTATION MARK
			0xE4	=>  0x2030,	# PER MILLE SIGN
			0xE5	=>  0x00C2,	# LATIN CAPITAL LETTER A WITH CIRCUMFLEX
			0xE6	=>  0x00CA,	# LATIN CAPITAL LETTER E WITH CIRCUMFLEX
			0xE7	=>  0x00C1,	# LATIN CAPITAL LETTER A WITH ACUTE
			0xE8	=>  0x00CB,	# LATIN CAPITAL LETTER E WITH DIAERESIS
			0xE9	=>  0x00C8,	# LATIN CAPITAL LETTER E WITH GRAVE
			0xEA	=>  0x00CD,	# LATIN CAPITAL LETTER I WITH ACUTE
			0xEB	=>  0x00CE,	# LATIN CAPITAL LETTER I WITH CIRCUMFLEX
			0xEC	=>  0x00CF,	# LATIN CAPITAL LETTER I WITH DIAERESIS
			0xED	=>  0x00CC,	# LATIN CAPITAL LETTER I WITH GRAVE
			0xEE	=>  0x00D3,	# LATIN CAPITAL LETTER O WITH ACUTE
			0xEF	=>  0x00D4,	# LATIN CAPITAL LETTER O WITH CIRCUMFLEX
			0xF0	=>  0xF8FF,	# Apple logo
			0xF1	=>  0x00D2,	# LATIN CAPITAL LETTER O WITH GRAVE
			0xF2	=>  0x00DA,	# LATIN CAPITAL LETTER U WITH ACUTE
			0xF3	=>  0x00DB,	# LATIN CAPITAL LETTER U WITH CIRCUMFLEX
			0xF4	=>  0x00D9,	# LATIN CAPITAL LETTER U WITH GRAVE
			0xF5	=>  0x0131,	# LATIN SMALL LETTER DOTLESS I
			0xF6	=>  0x02C6,	# MODIFIER LETTER CIRCUMFLEX ACCENT
			0xF7	=>  0x02DC,	# SMALL TILDE
			0xF8	=>  0x00AF,	# MACRON
			0xF9	=>  0x02D8,	# BREVE
			0xFA	=>  0x02D9,	# DOT ABOVE
			0xFB	=>  0x02DA,	# RING ABOVE
			0xFC	=>  0x00B8,	# CEDILLA
			0xFD	=>  0x02DD,	# DOUBLE ACUTE ACCENT
			0xFE	=>  0x02DB,	# OGONEK
			0xFF	=>  0x02C7	# CARON
		    )
	    ) ;


	public function  __construct ( $object_id, $font_variant )
	   {
		parent::__construct ( $object_id, $font_variant, self::$MacRomanCharacterMap ) ;
	    }
    }


/*==============================================================================================================

    class PdfTexterAdobeUndocumentedUnicodeMap -
        Sometimes, Unicode maps translate character ids to something in the range 0xF000..0xF0FF (or maybe more).
	These mapped characters do not correspond to anything else in Unicode, but rather to a special character
	set.
	This class is not meant to be instantiated by anything here, but rather used for its $Map property.
	Note that the $Map array is not complete.

  ==============================================================================================================*/
class	PdfTexterAdobeUndocumentedUnicodeMap		extends		PdfTexterAdobeMap
   {
	public static	$UnicodeMap		=  array
	   (
		0xF0F0 	 =>  0x30,	// '0' through '9'
		0xF0EF 	 =>  0x31,
		0xF0EE 	 =>  0x32,
		0xF0ED 	 =>  0x33,
		0xF0EC 	 =>  0x34,
		0xF0EB 	 =>  0x35,
		0xF0EA 	 =>  0x36,
		0xF0E9 	 =>  0x37,
		0xF0E8 	 =>  0x38,
		0xF0E7 	 =>  0x39,
		0xF0DF 	 =>  0x41,	// 'A' through 'Z'
		0xF0DE 	 =>  0x42,
		0xF0DD 	 =>  0x43,
		0xF0DC 	 =>  0x44,
		0xF0DB 	 =>  0x45,
		0xF0DA 	 =>  0x46,
		0xF0D9 	 =>  0x47,
		0xF0D8 	 =>  0x48,
		0xF0D7 	 =>  0x49,
		0xF0D6 	 =>  0x4A,
		0xF0D5 	 =>  0x4B,
		0xF0D4 	 =>  0x4C,
		0xF0D3 	 =>  0x4D,
		0xF0D2 	 =>  0x4E,
		0xF0D1 	 =>  0x4F,
		0xF0D0 	 =>  0x50,
		0xF0CF 	 =>  0x51,
		0xF0CE 	 =>  0x52,
		0xF0CD 	 =>  0x53,
		0xF0CC 	 =>  0x54,
		0xF0CB 	 =>  0x55,
		0xF0CA 	 =>  0x56,
		0xF0C9 	 =>  0x57,
		0xF0C8 	 =>  0x58,
		0xF0C7 	 =>  0x59,
		0xF0C6 	 =>  0x5A,
		0xF0BF 	 =>  0x61,	// 'a' through 'z'
		0xF0BE 	 =>  0x62,
		0xF0BD 	 =>  0x63,
		0xF0BC 	 =>  0x64,
		0xF0BB 	 =>  0x65,
		0xF0BA 	 =>  0x66,
		0xF0B9 	 =>  0x67,
		0xF0B8 	 =>  0x68,
		0xF0B7 	 =>  0x69,
		0xF0B6 	 =>  0x6A,
		0xF0B5 	 =>  0x6B,
		0xF0B4 	 =>  0x6C,
		0xF0B3 	 =>  0x6D,
		0xF0B2 	 =>  0x6E,
		0xF0B1 	 =>  0x6F,
		0xF0B0 	 =>  0x70,
		0xF0AF 	 =>  0x71,
		0xF0AE 	 =>  0x72,
		0xF0AD 	 =>  0x73,
		0xF0AC 	 =>  0x74,
		0xF0AB 	 =>  0x75,
		0xF0AA 	 =>  0x76,
		0xF0A9 	 =>  0x77,
		0xF0A8 	 =>  0x78,
		0xF0A7 	 =>  0x79,
		0xF0A6 	 =>  0x7A,
		0xF0F1 	 =>  0x2F,	// '/'
		0xF0E6 	 =>  0x3A,	// ':'
		0xF0F3 	 =>  0x2D,	// '-'
		0xF0F8 	 =>  0x28,	// '('
		0xF0F7 	 =>  0x29,	// ')'
		0xF0F2 	 =>  0x2E,	// '.'
		0xF020 	 =>  0x20,	// Space
		0xF0F9 	 =>  0x27,	// "'"
		0xF037 	 =>  0xE9,	// &eacute;
		0xF038 	 =>  0xE8,	// &egrave;
	    ) ;



	public function  __construct ( $object_id, $font_variant )
	   {
		parent::__construct ( $object_id, $font_variant, self::$UnicodeMap ) ;
	    }
    }


/*==============================================================================================================

    PdfTexterCIDMap -
        A class for mapping (or trying to...) CID fonts.

  ==============================================================================================================*/
abstract class	PdfTexterCIDMap		extends  PdfTexterCharacterMap
   {
	// CID maps are associative arrays whose keys are the font CID (currently expressed as a numeric value) and
	// whose values are the corresponding UTF8 representation. The following special values can also be used to
	// initialize certain entries :
	// UNKNOWN_CID :
	//	Indicates that the corresponding CID has no known UTF8 counterpart. When the PdfToText::$DEBUG variable
	//	is true, every character in this case will be replaced with the string : "[UID: abcd]", where "abcd" is
	//	the hex representation of the CID. This way, new CID tables can be built using this information.
	const		UNKNOWN_CID		=  -1 ;
	// ALT_CID :
	//	Sorry, this will remain undocumented so far and will be highligh subject to change, since it is dating
	//	from my first interpretation of CID fonts, which is probably wrong.
	const		ALT_CID			=  -2 ;


	// CID font map file ; the file is a PHP script that must contain an array of the form :
	//	$map	=  array
	//	   (
	//		'plain'		=>  array
	//		   ( 
	//			$cid1	=>  $utf1,
	//			...
	//		    )
	//	    ) ;
	protected	$MapFile ;
	// Map, loaded into memry
	protected	$Map ;
	// Map cache - the interest is to avoid unnecessary includes
	private static	$CachedMaps		=  array ( ) ;

	// Related to the first experimentatl implementation of CID fonts
	private		$LastAltOffset		=  false ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Loads the specified map.
		If the map files contains a definition such as :

			$map	=  'IDENTITY-H-GQJGLM.cid' ;

		then the specified map will be loaded instead (ony one ndirection is supported).
	    	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $object_id, $map_name, $font_variant )
	   {
		// Initialize parent objects
		parent::__construct ( $object_id ) ;
		$this -> HexCharWidth	=  4 ;			// So far, CIDs are 2-bytes long
		
		// Since alternate characters can be apparently prefixed by 0x0000 or 0x0001, two calls to the array access operator 
		// will be needed to retrieve the exact character in such cases
		// This is why we have to tell the upper layers not to cache the results
		$this -> Cache		=  false ;

		$map_index	=  "$map_name:$font_variant" ;

		// If this font has already been loaded somewhere, then reuse its information
		if  ( isset ( self::$CachedMaps [ $map_index] ) )
		   {
			$map	=  self::$CachedMaps [ $map_index ] [ 'map' ] ;
			$file	=  self::$CachedMaps [ $map_index ] [ 'file' ] ;
		    }
		// Otherwise, 
		else
		   {
			$file		=  $this -> __get_cid_file ( $map_name, $font_variant ) ;

			// No CID map found : CID numbers will be mapped as is
			if  ( ! file_exists ( $file ) )
			   {
				if  ( PdfToText::$DEBUG )
					warning ( new PdfToTextDecodingException ( "Could not find CID table \"$map_name\" in directory \"" . PdfToText::$CIDTablesDirectory . "\"." ) ) ;
			    }
			// Otherwise, load the CID map 
			else
			   {
				include ( $file ) ;

				if  ( isset ( $map ) )
				   {
					// We authorize one CID map to contain the name of another CID map file, instead of the map itself
					if  ( is_string ( $map ) )
					   {
						$file	=  PdfToText::$CIDTablesDirectory . "/$map" ;
						include ( $file ) ;
					    }

					if  ( isset ( $map ) )
						self::$CachedMaps [ $map_index ]	=  array ( 'file' => $file, 'map' => $map ) ;
				    }
				else if  ( PdfToText::$DEBUG )
					warning ( new PdfToTextDecodingException ( "CID \"$file\" does not contain any definition." ) ) ;
			    }
		    }

		// Save map info for this CID font
		$this -> MapFile	=  $file ;
		$this -> Map		=  ( isset ( $map ) ) ?  $map :  array ( ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    __get_cid_file -
		Searches in the CIDTables directory for the CID map that best matches the specified map name (usually,
		IDENTITY-H) and the optional font variant.

		If a font variant has been specified, like "ABCD+Italic-Arial", then the CID tables directory will be 
		searched for the following files, in the following order :
		- IDENTITY-H-ABCD+Italic-Arial.cid
		- IDENTITY-H-ABCD+Italic.cid
		- IDENTITY-H-ABCD.cid
		- If none found, then IDENTITY-H-empty.cid will be used and a warning will be issued in debug mode.

	 *-------------------------------------------------------------------------------------------------------------*/
	private function  __get_cid_file ( $map_name, $font_variant )
	   {
		$files		=  array ( ) ;

		// Search for font variants, if any
		if  ( $font_variant )
		   {
			if  ( preg_match ( '/^ (?P<name> [a-z_][a-z_0-9]*) (?P<rest> [\-+] .*) $/imsx' , $font_variant, $match ) ) 
			   {
				$basename	=  '-' . $match [ 'name' ] ;

				if  ( preg_match_all ( '/ (?P<sep> [\-+]) (?P<name> [^\-+]+) /ix', $match [ 'rest' ], $other_matches ) ) 
				   {
					for  ( $i = count ( $other_matches [ 'name' ] ) - 1 ; $i  >=  0 ; $i -- )
					   {
						$new_file	=  $basename ;

						for  ( $j = 0 ; $j  <  $i ; $j ++ )
							$new_file	.=  $other_matches [ 'sep' ] [$i] . $other_matches [ 'name' ] [$i] ;

						$files []		=  array ( PdfToText::$CIDTablesDirectory . "/$map_name$new_file.cid", 'standard' ) ;
					    }
				    }
			    }

			// Last one will be the empty CID font
			$files []	=  array ( PdfToText::$CIDTablesDirectory . "/IDENTITY-H-empty.cid", 'empty' ) ;
		    }
	
		// Add the specified map file
		$files []	=  array ( PdfToText::$CIDTablesDirectory . "/$map_name.cid", 'default' ) ;
		
		// The first existing file in the list should be the appropriate one
		foreach  ( $files  as  $file )
		   {
			if  ( file_exists ( $file [0] ) )
			   {
				if  ( PdfToText::$DEBUG )
				   {
					if  ( $file [1]  ===  'empty' )
						warning ( new PdfToTextDecodingException ( "Using empty IDENTITY-H definition for map \"$map_name\", variant \"$font_variant\"." ) ) ;
					else if  ( $file [1]  ===  'default' )
						warning ( new PdfToTextDecodingException ( "Using default IDENTITY-H definition for map \"$map_name\"." ) ) ;
				    }

				return ( $file [0] ) ;
			    }
		    }

		// No CID font found
		return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

	        Interface implementations.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( count ( $this -> Map ) ) ; }


	public function  offsetExists ( $offset )
	   { return ( isset ( $this -> Map [ 'plain' ] [ $offset ] ) ) ; }


	public function  offsetGet ( $offset )
	   {
		if  ( isset ( $this -> Map [ 'plain' ] [ $offset ] ) )
		   {
			$ch	=  $this -> Map [ 'plain' ] [ $offset ] ;

			switch  ( $ch )
			   {
				case	self::UNKNOWN_CID :
					if  ( PdfToText::$DEBUG )
						echo ( '[UID:' . sprintf ( '%04x', $offset ) . "]" ) ;

					$this -> LastAltOffset	=  false ;

					if  ( ! PdfToText::$DEBUG )
						return ( '' ) ;
					else 
						return ( '[UID:' . sprintf ( '%04x', $offset ) . "]" ) ;

				case	self::ALT_CID :
					$this -> LastAltOffset		=  ( integer ) $offset ;

					return ( '' ) ;

				default :
					if  ( $this -> LastAltOffset  ===  false ) 
						return ( $ch ) ;

					if  ( isset ( $this -> Map [ 'alt' ] [ $this -> LastAltOffset ] [ $offset ] ) )
					   {
						$ch2	=  $this -> Map [ 'alt' ] [ $this -> LastAltOffset ] [ $offset ] ;

						if  ( $ch2  ==  self::UNKNOWN_CID )
						   {
							if  ( PdfToText::$DEBUG )
							   {
								echo ( "[CID{$this -> LastAltOffset}:" . sprintf ( '%04x', $offset ) . "]" ) ;

								$ch2  =  "[CID{$this -> LastAltOffset}: $offset]" ;
							    }
						    }
					    }
					else
						$ch2	=  '' ;

					$this -> LastAltOffset	=  false ;

					return ( $ch2 ) ;
			    }
		    }
		else
		   {
			$this -> LastAltOffset	=  false ;
			
			return ( '' ) ;
		    }
	    }
    }



/*==============================================================================================================

    PdfTexterIdentityHCIDMap -
        A class for mapping IDENTITY-H CID fonts (or trying to...).

  ==============================================================================================================*/
class  PdfTexterIdentityHCIDMap		extends  PdfTexterCIDMap
   {
	public function  __construct ( $object_id, $font_variant )
	   {
		parent::__construct ( $object_id, 'IDENTITY-H', $font_variant ) ;
	    }
    }



/*==============================================================================================================

    PdfTexterPageMap -
        A class for detecting page objects mappings and retrieving page number for a specified object.
	There is a quadruple level of indirection here :

	- The first level contains a /Type /Catalog parameter, with a /Pages one that references an object which
	  contains a /Count and /Kids. I don't know yet if the /Pages parameter can reference more than one
	  object using the array notation. However, the class is designed to handle such situations.
	- The object containing the /Kids parameter references objects who, in turn, lists the objects contained
	  into one single page.
	- Each object referenced in /Kids has a /Type/Page parameter, together with /Contents, which lists the
	  objects of the current page.

	Object references are of the form : "x y R", where "x" is the object number.

	Of course, anything can be in any order, otherwise it would not be funny ! Consider the following 
	example :

		(1) 5 0 obj
			<< ... /Pages 1 0 R ... >>
		    endobj

		(2) 1 0 obj
			<< ... /Count 1 /Kids[6 0 R] ... /Type/Pages ... >>
		    endobj

		(3)  6 0 obj
			<< ... /Type/Page ... /Parent 1 0 R ... /Contents [10 0 R 11 0 R ... x 0 R]
		     endobj

	Object #5 says that object #1 contains the list of page contents (in this example, there is only one page,
	referenced by object #6).
	Object #6 says that the objects #10, #11 through #x are contained into the same page.
	The quadruple indirection comes when you are handling one of the objects referenced in object #6 and you
	need to retrieve their page number...

	Of course, you cannot rely on the fact that all objects appear in logical order.

	And, of course #2, there may be no page catalog at all ! in such cases, objects containing drawing 
	instructions will have to be considered as a single page, whose number will be sequential.

	And, of course #3, as this is the case with the official PDF 1.7 Reference from Adobe, there can be a
	reference to a non-existing object which was meant to contain the /Kids parameter (!). In this case,
	taking the ordinal number of objects of type (3) gives the page number minus one.

	One mystery is that the PDF 1.7 Reference file contains 1310 pages but only 1309 are recognized here...

  ==============================================================================================================*/
class  PdfTexterPageMap		extends  PdfObjectBase
   {
	// Page contents are (normally) first described by a catalog
	// Although there should be only one entry for that, this property is defined as an array, as you need to really
	// become paranoid when handling pdf contents...
	protected	$PageCatalogs		=  array ( ) ;
	// Entries that describe which page contains which text objects. Of course, these can be nested otherwise it would not be funny !
	protected	$PageKids		=  array ( ) ;
	// Terminal entries : they directly give the ids of the objects belonging to a page
	public		$PageContents		=  array ( ) ;
	// Note that all the above arrays are indexed by object id and filled with the data collected by calling the Peek() Method...

	// Objects that could be referenced from other text objects as XObjects, using the /TPLx notation
	protected	$TemplateObjects	=  array ( ) ;

	// Once the Peek() method has collected page contents & object information, the MapCatalog() method is called to create this array
	// which contains page numbers as keys, and the list of objects contained in this page as values
	public		$Pages			=  array ( ) ;
	// Holds page attributes
	public		$PageAttributes		=  array ( ) ;

	// Resource mappings can either refer to an object (/Resources 2 0 R) or to inline mappings (/Resources << ... >>)
	// The same object can be referenced by many /Resources parameters throughout the pdf file, so its important to keep
	// the analyzed mappings in a cache, so that later references will reuse the results of the first one
	private		$ResourceMappingCache	=  array ( ) ;
	// List of XObject names - Used by the IsValidTemplate() function
	private		$XObjectNames		=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR
		Creates a PdfTexterPageMap object. Actually, nothing significant is perfomed here, as this class' goal
		is to be used internally by PdfTexter.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( )
	   {
		parent::__construct ( ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        AddTemplateObject - Adds an object that could be referenced as a template/
	
	    PROTOTYPE
	        $pagemap -> AddTemplateObject ( $object_id, $object_text_data ) ;
	
	    DESCRIPTION
	        Adds an object that may be referenced as a template from another text object, using the /TPLx notation.
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the object that may contain a resource mapping entry.

		$object_data (string) -
			Object contents.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  AddTemplateObject ( $object_id, $object_text_data ) 
	   {
		$this -> TemplateObjects [ $object_id ]		=  $object_text_data ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetResourceMappings - Gets resource mappings specified after a /Resources parameter.
	
	    PROTOTYPE
	        $result		=  $this -> GetResourceMappings ( $object_id, $object_data, $parameter, $pdf_object_list ) ;
	
	    DESCRIPTION
	        Most of the time, objects containing a page description (/Type /Page) also contain a /Resources parameter,
		which may be followed by one of the following constructs :
		- A reference to an object, such as :
			/Resources 2 0 R
		- Or an inline set of parameters, such as font or xobject mappings :
			/Resources << /Font<</F1 10 0 R ...>> /XObject <</Im0 27 0 R ...>>
		This method extracts alias/object mappings for the parameter specified by $parameter (it can be for
		example 'Font' or 'Xobject') and returns these mappings as an associative array.
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the object that may contain a resource mapping entry.

		$object_data (string) -
			Object contents.

		$parameter (string) -
			Parameter defining resource mapping, for example /Font or /XObject.

		$pdf_object_list (associative array) -
			Array of object id/object data associations, for all objects defined in the pdf file.
	
	    RETURN VALUE
	        The list of resource mappings for the specified parameter, as an associative array, whose keys are the
		resource aliases and values are the corresponding object ids.
		The method returns an empty array if the specified object does not contain resource mappings or does
		not contain the specified parameter.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetResourceMappings ( $object_id, $object_data, $parameter, $pdf_object_list )
	   {
		// The /Resources parameter refers to an existing PDF object 
		if  ( preg_match ( '#/Resources \s* (?P<object_id> \d+) \s+ \d+ \s+ R#ix', $object_data, $match ) )
		   {
			// Return the cached result if the same object has previously been referenced by a /Resources parameter
			if  ( isset ( $this -> ResourceMappingCache [ $object_id ] [ $parameter ] ) )
				return ( $this -> ResourceMappingCache [ $object_id ] [ $parameter ] ) ;

			// Check that the object that is referred to exists
			if  ( isset ( $pdf_object_list [ $match [ 'object_id' ] ] ) )
				$data	=  $pdf_object_list [ $match [ 'object_id' ] ] ;
			else
				return ( array ( ) ) ;

			$is_object	=  true ;	// to tell that we need to put the results in cache for later use
		    }
		// The /Resources parameter is followed by inline mappings
		else if  ( preg_match ( '#/Resources \s* <#ix', $object_data, $match, PREG_OFFSET_CAPTURE ) )
		   {
			$data		=  substr ( $object_data, $match [0] [1] + strlen ( $match [0] [0] ) - 1 ) ;
			$is_object	=  false ;
		    }
		else
			return ( array ( ) ) ;

		// Whatever we will be analyzing (an object contents or inline contents following the /Resources parameter),
		// the text will be enclosed within double angle brackets (<< ... >>)

		// A small kludge for /XObject which specify an object reference ("15 0 R") instead of XObjects mappings
		// ("<< ...>>" )
		if  ( $parameter   ==  '/XObject'  &&  preg_match ( '#/XObject \s+ (?P<obj> \d+) \s+ \d+ \s+ R#ix', $data, $match ) )
		   {
			$data = '/XObject ' . $pdf_object_list [ $match [ 'obj' ] ] ;
		    }
		
		if  ( preg_match ( "#$parameter \s* << \s* (?P<mappings> .*?) \s* >>#imsx", $data, $match ) )
		   {
			preg_match_all ( '# (?P<mapping> / [^\s]+) \s+ (?P<object_id> \d+) \s+ \d+ \s+ R#ix', $match [ 'mappings' ], $matches ) ;
			
			$mappings	=  array ( ) ;

			// Mapping extraction loop
			for  ( $i = 0, $count = count ( $matches [ 'object_id' ] ) ; $i  <  $count ; $i ++ )
				$mappings [ $matches [ 'mapping' ] [$i] ]	=  $matches [ 'object_id' ] [$i] ;

			// Put results for referenced objects in cache
			if  ( $is_object )
				$this -> ResourceMappingCache [ $object_id ] [ $parameter ]	=  $mappings ;

			return ( $mappings ) ;
		    }
		else
			return ( array ( ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Peek - Peeks page information from a pdf object.
	
	    PROTOTYPE
	        $pagemap -> Peek ( ) ;
	
	    DESCRIPTION
	        Retrieves page information which can be of type (1), (2) or (3), as described in the class comments.
	
	    PARAMETERS
	        $object_id (integer) -
	                Id of the current pdf object.

		$object_data (string) -
			Pdf object contents.

		$pdf_objects (associative array) -
			Objects defined in the pdf file, as an associative array whose keys are object numbers and
			values object data.
			This parameter is used for /Type/Page objects which have a /Resource parameter that references
			an existing object instead of providing font mappings and other XObject mappings inline, 
			enclosed within double angle brackets (<< /Font ... >>).
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  Peek ( $object_id, $object_data, $pdf_objects )
	   {
		// Page catalog (/Type/Catalog and /Pages x 0 R)
		if  ( preg_match ( '#/Type \s* /Catalog#ix', $object_data )  &&  $this -> GetObjectReferences ( $object_id, $object_data, '/Pages', $references ) )
			$this -> PageCatalogs	=  array_merge ( $this -> PageCatalogs, $references ) ;
		// Object listing the object numbers that give the list of objects contained in a single page (/Types/Pages and /Count x /Kids[x1 0 R ... xn 0 R]
		else if  ( preg_match ( '#/Type \s* /Pages#ix', $object_data ) )
		   {
			if  ( $this -> GetObjectReferences ( $object_id, $object_data, '/Kids', $references ) )
			   {
				// Sometimes, a reference can be the one of an object that contains the real reference ; in the following example,
				// the actual page contents are not in object 4, but in object 5
				//	/Kids 4 0 R
				//	...
				//	4 0 obj
				//	[5 0 R]
				//	endobj
				$new_references		=  array ( ) ;

				foreach  ( $references  as  $reference )
				   {
					if  ( ! isset ( $pdf_objects [ $reference ] )  ||  
						! preg_match ( '/^ \s* (?P<ref> \[ [^]]+ \]) \s*$/imsx', $pdf_objects [ $reference ], $match ) )
					   {
						$new_references []	=  $reference ;
					    }
					else
					   {
						$this -> GetObjectReferences ( $reference, $pdf_objects [ $reference ], '', $sub_references ) ;
						$new_references		=  array_merge ( $new_references, $sub_references ) ;
					    }

				    }

				// Get kid count (knowing that sometimes, it is missing...)
				preg_match ( '#/Count \s+ (?P<count> \d+)#ix', $object_data, $match ) ;
				$page_count				=  ( isset ( $match [ 'count' ] ) ) ?  ( integer ) $match [ 'count' ] : false ;
				
				// Get parent object id
				preg_match ( '#/Parent \s+ (?P<parent> \d+)#ix', $object_data, $match ) ;
				$parent					=  ( isset ( $match [ 'parent' ] ) ) ?  ( integer ) $match [ 'parent' ] : false ;

				$this -> PageKids [ $object_id ]	=  array
				   (
					'object'	=>  $object_id,
					'parent'	=>  $parent,
					'count'		=>  $page_count,
					'kids'		=>  $new_references 
				    ) ;
			    }
		    }
		// Object listing the other objects that are contained in this page (/Type/Page and /Contents[x1 0 R ... xn 0 R]
		else if  ( preg_match ( '#/Type \s* /Page\b#ix', $object_data ) )
		   {
			if  ( $this -> GetObjectReferences ( $object_id, $object_data, '/Contents', $references ) )
			   {
				preg_match ( '#/Parent \s+ (?P<parent> \d+)#ix', $object_data, $match ) ;
				$parent					=  ( isset ( $match [ 'parent' ] ) ) ?  (integer) $match [ 'parent' ] : false ;
				$fonts					=  $this -> GetResourceMappings ( $object_id, $object_data, '/Font', $pdf_objects ) ;
				$xobjects				=  $this -> GetResourceMappings ( $object_id, $object_data, '/XObject', $pdf_objects ) ;

				// Find the width and height of the page (/Mediabox parameter) 
				if  ( preg_match ( '#/MediaBox \s* \[ \s* (?P<x1> \d+) \s+ (?P<y1> \d+) \s+ (?P<x2> \d+) \s+ (?P<y2> \d+) \s* \]#imsx', $object_data, $match ) )
				   {
					$width		=  ( double ) ( $match [ 'x2' ] - $match [ 'x1' ] + 1 ) ;
					$height		=  ( double ) ( $match [ 'y2' ] - $match [ 'y1' ] + 1 ) ;
				    }
				// Otherwise, fix an arbitrary width and length (but this should never happen, because all pdf files are correct, isn't it?)
				else
				   {
					$width		=  595 ;
					$height		=  850 ;
				    }

				// Yes ! some /Contents parameters may designate another object which contains references to the real text contents
				// in the form : [x 0 R y 0 R etc.], so we have to dig into it...
				$new_references				=  array ( ) ;

				foreach  ( $references  as  $reference )
				   {
					// We just need to check that the object contains something like :
					//	[x 0 R y 0 R ...]
					// and nothing more 
					if  ( isset ( $pdf_objects [ $reference ] )  &&  preg_match ( '#^\s* \[ [^]]+ \]#x', $pdf_objects [ $reference ] )  &&
							$this -> GetObjectReferences ( $reference, $pdf_objects [ $reference ], '', $nested_references ) )
						$new_references		=  array_merge ( $new_references, $nested_references ) ;
					else
						$new_references []	=  $reference ;
				    }

				$this -> PageContents [ $object_id ]	=  array
				   (
					'object'	=>  $object_id,
					'parent'	=>  $parent,
					'contents'	=>  $new_references,
					'fonts'		=>  $fonts,
					'xobjects'	=>  $xobjects,
					'width'		=>  $width,
					'height'	=>  $height
				    ) ;
			    }
		    }
		// None of the above, but object contains /Xobject's and maybe more...
		else if  ( preg_match ( '#/Type \s* /XObject\b#ix', $object_data ) )
		   {
			preg_match ( '#/Parent \s+ (?P<parent> \d+)#ix', $object_data, $match ) ;
			$parent					=  ( isset ( $match [ 'parent' ] ) ) ?  (integer) $match [ 'parent' ] : false ;
			$fonts					=  $this -> GetResourceMappings ( $object_id, $object_data, '/Font', $pdf_objects ) ;
			$xobjects				=  $this -> GetResourceMappings ( $object_id, $object_data, '/XObject', $pdf_objects ) ;

			$this -> GetObjectReferences ( $object_id, $object_data, '/Contents', $references ) ;

			$this -> PageContents [ $object_id ]	=  array
			   (
				'object'	=>  $object_id,
				'parent'	=>  $parent,
				'contents'	=>  $references,
				'fonts'		=>  $fonts,
				'xobjects'	=>  $xobjects
			    ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        ProcessTemplateReferences - Replace template references with actual text contents.
	
	    PROTOTYPE
	        $text		=  $pagemap -> ReplaceTemplateReferences ( $page_number, $text_data ) ;
	
	    DESCRIPTION
	        Replaces template references of the form "/TPLx Do" with the actual text contents.
	
	    PARAMETERS
	        $page_number (integer) -
	                Page number of the object that contains the supplied object data.

		$text_data (string)
			Text drawing instructions that are to be processed.
	
	    RETURN VALUE
	        Returns the original text, where all template references have been replaced with the contents of the
		object they refer to.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  ProcessTemplateReferences ( $page_number, $text_data ) 
	    {
		// Many paranoid checks in this piece of code...
		if  ( isset ( $this -> Pages [ $page_number ] ) )
		   {
			// Loop through the PageContents array to find which one(s) may be subject to template reference replacements
			foreach  ( $this -> PageContents  as  $page_contents )
			   {
				// If the current object relates to the specified page number, AND it has xobjects, then the supplied text data
				// may contain template reference of the form : /TPLx.
				// In this case, we replace such a reference with the actual contents of the object they refer to
				if  ( isset ( $page_contents [ 'page' ] )  &&  $page_contents [ 'page' ]  ==  $page_number  &&  count ( $page_contents [ 'xobjects' ] ) )
				   {
					$template_searches	=  array ( ) ;
					$template_replacements	=  array ( ) ;
					
					$this ->  __get_replacements ( $page_contents, $template_searches, $template_replacements ) ;
					$text_data	=  self::PregStrReplace ( $template_searches, $template_replacements, $text_data ) ;
				    }
			    }
		    }

		return ( $text_data ) ;
	     }


	// __get_replacements -
	//	Recursively gets the search/replacement strings for template references.
	private function  __get_replacements ( $page_contents, &$searches, &$replacements, $objects_seen = array ( ) )
	   {
		foreach  ( $page_contents [ 'xobjects' ]  as  $template_name => $template_object )
		   {
			if  ( isset ( $this -> TemplateObjects [ $template_object ] )  &&  ! isset ( $objects_seen [ $template_object ] ) )
			   {
				$template				=  $this -> TemplateObjects [ $template_object ] ;
				$searches []				=  '#(' . $template_name . ' \s+ Do\b )#msx' ;
				$replacements []			=  '!PDFTOTEXT_TEMPLATE_' . substr ( $template_name, 1 ) . ' ' . $template ;
				$objects_seen [ $template_object ]	=  $template_object ;
			
				if  ( isset ( $this -> PageContents [ $template_object ] ) )
					$this -> __get_replacements ( $this -> PageContents [ $template_object ], $searches, $replacements, $objects_seen ) ;
			    }
		    }
	    }



	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        MapObjects - Builds a correspondance between object and page numbers.
	
	    PROTOTYPE
	        $pagemap -> MapObjects ( ) ;
	
	    DESCRIPTION
	        Builds a correspondance between object and page numbers. The page number corresponding to an object id 
		will after that be available using the array notation.

	    NOTES
		This method behaves as if there could be more than one page catalog in the same file, but I've not yet
		encountered this case.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  MapObjects ( $objects )
	   {
		$kid_count	=  count ( $this -> PageKids ) ;

		// PDF files created short after the birth of Earth may have neither a page catalog nor page contents descriptions
		if  ( ! count ( $this -> PageCatalogs  ) )
		   {
			// Later, during Pleistocen, references to page kids started to appear...
			if  ( $kid_count )
			   {
				foreach  ( array_keys ( $this -> PageKids )  as  $catalog )
					$this -> MapKids ( $catalog, $current_page ) ;
			    }
			else
				$this -> Pages [1]	=  array_keys ( $objects ) ;
		    }
		// This is the ideal situation : there is a catalog that allows us to gather indirectly all page data
		else
		   {
			$current_page		=  1 ;

			foreach  ( $this -> PageCatalogs  as  $catalog )
			   {
				if  ( isset ( $this -> PageKids [ $catalog ] ) )
					$this -> MapKids ( $catalog, $current_page ) ;
				// Well, almost ideal : it may happen that the page catalog refers to a non-existing object :
				// in this case, we behave the same as if there were no page catalog at all : group everything
				// onto one page
				else 
					$this -> Pages [1]	=  array_keys ( $objects ) ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        MapKids - Establishes a correspondance between page kids and a current page number.
	
	    PROTOTYPE
	        $pagemap -> MapObjects ( $catalog, &$page ) ;
	
	    DESCRIPTION
	  	Tries to assign a page number to all page description objects that have been collected by the Peek() 
		method.
	  	Also creates the Pages associative array, whose keys are page numbers and whose values are the ids of 
		the objects that the page contains.
	  
	    EXAMPLE 
	  	The following example gives an overview of a possible layout for page catalogs ; it describes which 
		objects contain	what. 
	  	Lines starting with "#x", where "x" is a number, stands for a PDF object definition, which will start 
		with "x 0 obj" in the PDF file.
	  	Whenever numbers are referenced (other than those prefixed with a "#"), it means "reference to the 
		specified object.
	  	For example, "54" will refer to object #54, and will be given as "54 0 R" in the PDF file.
	  	The numbers at the beginning of each line are just "step numbers", which will be referenced in the 
		explanations after the example :

			(01) #1 : /Type/Catalog /Pages 54
			(02)    -> #54 : /Type/Pages /Kids[3 28 32 58] /Count 5
			(03)           -> #3 : /Type/Page /Parent 54 /Contents[26]
			(04)		     -> #26 : page contents
			(05)           -> #28 : /Type/Page /Parent 54 /Contents[30 100 101 102 103 104]
			(06)		     -> #30 : page contents
			(07)	       -> #32 : /Type/Page /Parent 54 /Contents[34]
			(08)		     -> #34 : page contents
			(09)	       -> #58 : /Type/Pages /Parent 54 /Count 2 /Kids[36 40]
			(10)		     -> #36 : /Type/Page /Parent 58 /Contents[38]
			(11)			    -> #38 : page contents
			(12)		     -> #40 : /Type/Page /Parent 58 /Contents[42]
			(13)			    -> #42 : page contents

		 Explanations :
			(01) Object #1 contains the page catalog ; it states that a further description of the page 
			     contents is given by object #54.
			     Note that it could reference multiple page descriptions, such as : /Pages [54 68 99...]
			     (although I did not met the case so far)
			(02) Object #54 in turn says that it as "kids", described by objects #3, #28, #32 and #58. It 
			     also says that it has 5 pages (/Count parameter) ; but wait... the /Kids parameter references 
			     4 objects while the /Count parameter states that we have 5 pages : what happens ? we will 
			     discover it in the explanations below.
			(03) Object #3 states that it is aimed for page description (/Type/Page) ; the page contents 
			     will be found in object #26, specified after the /Contents parameter. Note that here again, 
			     multiple objects could be referenced by the /Contents parameter but, in our case, there is 
			     only one, 26. Object #3 also says that its parent object (in the page catalog) is object 
			     #54, defined in (01).
			     Since this is the first page we met, it will have page number 1.
			(04) ... object #26 contains the Postscript instructions to draw page #1
			(05) Object #28 has the same type as #3 ; its page contents can be located in object #30 (06)
			     The same applies for object #32 (07), whose page contents are given by object #34 (08).
			     So, (05) and (07) will be pages 2 and 3, respectively.
			(09) Now, it starts to become interesting : object #58 does not directly lead to an object 
			     containing Postscript instructions as did objects #3, #28 and #32 whose parent is #54, but 
			     to yet another page catalog which contains 2 pages (/Count 2), described by objects #36 and 
			     #40. It's not located at the same position as object #54 in the hierarchy, so it shows that
			     page content descriptions can be recursively nested.
			(10) Object #36 says that we will find the page contents in object #38 (which will be page 4)
			(12) ... and object #40 says that we will find the page contents in object #42 (and our final 
			     page, 5)

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  MapKids ( $catalog, &$page ) 
	   {
		if  ( ! isset ( $this -> PageKids [ $catalog ] ) )
			return ;

		$entry		=  $this -> PageKids [ $catalog ] ;

		// The PDF file contains an object containing a /Type/Pages/Kids[] construct, specified by another object containing a
		// /Type/Catalog/Pages construct : we will rely on its contents to find which page contains what
		if  ( isset ( $this -> PageContents [ $entry [ 'kids' ] [0] ] ) )
		   {
			foreach  ( $entry [ 'kids' ]  as  $item )
			   {
				// Some objects given by a /Page /Contents[] construct do not directly lead to an object describing PDF contents,
				// but rather to an object containing in turn a /Pages /Kids[] construct ; this adds a level of indirection, and
				// we have to recursively process it
				if  ( isset ( $this -> PageKids [ $item ] ) )
				   {
					$this -> MapKids ( $item, $page ) ;
				    }
				// The referenced object actually defines page contents (no indirection)
				else
				   {
					$this -> PageContents [ $item ]	[ 'page' ]	=  $page ;
					$this -> Pages [ $page ]			=  ( isset ( $this -> PageContents [ $item ] [ 'contents' ] ) ) ? 
												$this -> PageContents [ $item ] [ 'contents' ] : array ( ) ;
					if ( isset ( $this -> PageContents [ $item ] [ 'width' ] ) )
					   {
						$this -> PageAttributes [ $page ]		=  array
						   (
							'width'			=>  $this -> PageContents [ $item ] [ 'width' ],
							'height'		=>  $this -> PageContents [ $item ] [ 'height' ]
						    ) ;
					    }

					$page ++ ;
				    }
			    }
		    }
		// No page catalog at all : consider everything is on the same page (this class does not use the WheresMyCrystalBall trait)
		else
		   {
			foreach  ( $entry [ 'kids' ]  as  $kid )
				$this -> MapKids ( $kid, $page ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetMappedFonts - Retrieves the mapped fonts per page
	
	    PROTOTYPE
	        $array	=  $pagemap -> GetMappedFonts ( ) ;
	
	    DESCRIPTION
	        Gets the mapped fonts, per page. XObjects are traversed, to retrieved additional font aliases defined
		by them.
		This function is used by the PdfTexter class to add additional entries to the FontMap object, 
		ensuring that each reference to a font remains local to a page.
	
	    RETURN VALUE
	        Returns an array of associative arrays which have the following entries :
		- 'page' :
			Page number.
		- 'xobject-name' :
			XObject name, that can define further font aliases. This entry is set to the empty string for
			global font aliases.
		- 'font-name' :
			Font name (eg, "/F1", "/C1_0", etc.).
		- 'object' :
			Object defining the font attributes, such as character map, etc.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetMappedFonts ( )
	   {
		$mapped_fonts	=  array ( ) ;
		$current_page	=  0 ;

		foreach  ( $this -> PageCatalogs  as  $catalog )
		   {
			if  ( ! isset ( $this -> PageKids [ $catalog ] ) )
				continue ;

			foreach  ( $this -> PageKids [ $catalog ] [ 'kids' ]  as  $page_object )
			   {
				$current_page ++ ;

				if  ( isset ( $this -> PageContents [ $page_object ] ) )
				   {
					$page_contents	=  $this -> PageContents [ $page_object ] ;
					$associations	=  array ( ) ;

					if  ( isset ( $page_contents [ 'fonts' ] ) )
					   {
						foreach  ( $page_contents [ 'fonts' ]  as  $font_name => $font_object )
						   {
							$mapped_fonts []		=  array 
							   ( 
								'page'		=>  $current_page, 
								'xobject-name'	=>  '',
								'font-name'	=>  $font_name, 
								'object'	=>  $font_object 
							    ) ;

							$associations [ ":$font_name" ]	=  $font_object ;

							$this -> __map_recursive ( $current_page, $page_contents [ 'xobjects' ], $mapped_fonts, $associations ) ;
						    }
					    }
				    }
			    }
		    }

		return ( $mapped_fonts ) ;
	    }

	
	// __map_recursive -
	//	Recursively collects font aliases for XObjects.
	private function  __map_recursive ( $page_number, $xobjects, &$mapped_fonts, &$associations )
	   {
		foreach  ( $xobjects  as  $xobject_name => $xobject_value ) 
		   {
			if  ( isset ( $this -> PageContents [ $xobject_value ] ) )
			   {
				foreach  ( $this -> PageContents [ $xobject_value ] [ 'fonts' ]  as  $font_name => $font_object )
				   {
					if  ( ! isset ( $associations [ "$xobject_name:$font_name" ] ) )
					   {
						$mapped_fonts []		=  array 
						   ( 
							'page'		=>  $page_number, 
							'xobject-name'	=>  $xobject_name, 
							'font-name'	=>  $font_name, 
							'object'	=>  $font_object 
						    ) ;

						$associations [ "$xobject_name:$font_name" ]	=  $font_object ;
					    }
				    }

				$this -> XObjectNames [ $xobject_name ]		=  1 ;
				$this -> __map_recursive ( $page_number, $this -> PageContents [ $xobject_value ] [ 'xobjects' ], $mapped_fonts, $associations ) ;
			    }
		    }
	    }


	
	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        IsValidXObject - Checks if the specified object is a valid XObject.
	
	    PROTOTYPE
	        $status		=  $pagemap -> IsValidXObjectName ( $name ) ;
	
	    DESCRIPTION
	        Checks if the specified name is a valid XObject defining its own set of font aliases.
	
	    PARAMETERS
	        $name (string) -
	                Name of the XObject to be checked.
	
	    RETURN VALUE
	        Returns true if the specified XObject exists and defines its own set of font aliases, false otherwise.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  IsValidXObjectName ( $name )
	   { return ( isset ( $this -> XObjectNames [ $name ] ) ) ; }
    }
    

/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                         IMAGE MANAGEMENT                                         ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    class PdfImage -
        Holds image data coming from pdf.

  ==============================================================================================================*/
abstract class  PdfImage			extends  PdfObjectBase 
   {
	// Image resource that can be used to process image data, using the php imagexxx() functions
	public		$ImageResource		=  false ;
	// Original image data
	protected	$ImageData ;
	// Tells if the image resource has been created - false when the autosave feature is on and the image is pure JPEG data
	protected	$NoResourceCreated ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR
	        Creates a PdfImage object with a resource that can be used with imagexxx() php functions.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $image_data, $no_resource_created = false )
	   {
		$this -> ImageData		=  $image_data ;
		$this -> NoResourceCreated	=  $no_resource_created ;

		if  ( ! $no_resource_created )
			$this -> ImageResource		=  $this -> CreateImageResource ( $image_data ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    DESTRUCTOR
	        Destroys the associated image resource.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __destruct ( )
	   {
		$this -> DestroyImageResource ( ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        CreateImageResource - creates an image resource from the supplied image data.
	
	    PROTOTYPE
	        $resource	=  $this -> CreateImageResource ( $data ) ;
	
	    DESCRIPTION
	        Creates an image resource from the supplied image data.
		Whatever the input format, the internal format will be the one used by the gd library.
	
	    PARAMETERS
	        $data (string) -
	                Image data.

	 *-------------------------------------------------------------------------------------------------------------*/
	abstract protected function  CreateImageResource ( $image_data ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        DestroyImageResource - Destroys the allocated image resource.
	
	    PROTOTYPE
	        $this -> DestroyImageResource ( ) ;
	
	    DESCRIPTION
	        Destroys the allocated image resource, using the libgd imagedestroy() function. This method can be 
		overridden by derived class if the underlying image resource does not come from the gd lib.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  DestroyImageResource ( )
	   {
		if  ( $this -> ImageResource )
			imagedestroy ( $this -> ImageResource ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        SaveAs - Saves the current image to a file.
	
	    PROTOTYPE
	        $pdfimage -> SaveAs ( $output_file, $image_type = IMG_JPEG ) ;
	
	    DESCRIPTION
	        Saves the current image resource to the specified output file, in the specified format.
	
	    PARAMETERS
	        $output_file (string) -
	                Output filename.

		$image_type (integer) -
			Output format. Can be any of the predefined php constants IMG_*.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SaveAs ( $output_file, $image_type = IMG_JPEG )
	   {
		if  ( ! $this -> ImageResource ) 
		   {
			if  ( $this -> NoResourceCreated  &&  $image_type  ==  IMG_JPEG )
				file_put_contents ( $output_file, $this -> ImageData ) ;
			else if  ( PdfToText::$DEBUG )
				warning ( new PdfToTextDecodingException ( "No image resource allocated." ) ) ;

			return ;
		    }

		$image_types		=  imagetypes ( ) ;

		switch  ( $image_type )
		   {
			case	IMG_JPEG :
			case	IMG_JPG :
				if  ( ! ( $image_types & IMG_JPEG )  &&  ! ( $image_types & IMG_JPG ) )
					error ( new PdfToTextDecodingException ( "Your current PHP version does not support JPG images." ) ) ;

				imagejpeg ( $this -> ImageResource, $output_file, 100 ) ;
				break ;

			case	IMG_GIF :
				if  ( ! ( $image_types & IMG_GIF ) )
					error ( new PdfToTextDecodingException ( "Your current PHP version does not support GIF images." ) ) ;

				imagegif ( $this -> ImageResource, $output_file ) ;
				break ;

			case	IMG_PNG :
				if  ( ! ( $image_types & IMG_PNG ) )
					error ( new PdfToTextDecodingException ( "Your current PHP version does not support PNG images." ) ) ;

				imagepng ( $this -> ImageResource, $output_file, 0 ) ;
				break ;
				
			case	IMG_WBMP :
				if  ( ! ( $image_types & IMG_WBMP ) )
					error ( new PdfToTextDecodingException ( "Your current PHP version does not support WBMP images." ) ) ;

				imagewbmp ( $this -> ImageResource, $output_file ) ;
				break ;
				
			case	IMG_XPM :
				if  ( ! ( $image_types & IMG_XPM ) )
					error ( new PdfToTextDecodingException ( "Your current PHP version does not support XPM images." ) ) ;

				imagexbm ( $this -> ImageResource, $output_file ) ;
				break ;

			default :
				error ( new PdfToTextDecodingException ( "Unknown image type #$image_type." ) ) ;
		    }
	    }


	public function  Output ( )
	   {
		$this -> SaveAs ( null ) ;
	    }
    }



/*==============================================================================================================

    class PdfJpegImage -
        Handles encoded JPG images.

  ==============================================================================================================*/
class  PdfJpegImage		extends  PdfImage 
   {
	public function  __construct ( $image_data, $autosave )
	   {
		parent::__construct ( $image_data, $autosave ) ;
	    }


	protected function  CreateImageResource ( $image_data )
	   {
		return ( imagecreatefromstring ( $image_data ) ) ;
	    }
    }


/*==============================================================================================================

    class PdfInlinedImage -
        Decodes raw image data in objects having the /FlateDecode flag.

  ==============================================================================================================*/
class  PdfInlinedImage		extends  PdfImage
   {
	// Supported color schemes
	const		COLOR_SCHEME_RGB		=  1 ;
	const		COLOR_SCHEME_CMYK		=  2 ;
	const		COLOR_SCHEME_GRAY		=  3 ;

	// Color scheme names, for debugging only
	private static	$DecoderNames		=  array
	   (
		self::COLOR_SCHEME_RGB		=>  'RGB',
		self::COLOR_SCHEME_CMYK		=>  'CMYK',
		self::COLOR_SCHEME_GRAY		=>  'Gray'
	    ) ;

	// Currently implemented image decoders
	private static	$Decoders		=  array
	   (
		self::COLOR_SCHEME_RGB		=>  array
		   (
			8	=>  '__decode_rgb8'
		    ),
		self::COLOR_SCHEME_GRAY		=>  array
		   (
			8	=>  '__decode_gray8'
		    ),
		self::COLOR_SCHEME_CMYK		=>  array
		   (
			8	=>  '__decode_cmyk8'
		    ),
	    ) ;

	// Image width and height
	public		$Width, 
			$Height ;
	// Color scheme
	public		$ColorScheme ;
	// Number of bits per color component
	public		$BitsPerComponent ;
	// Decoding function, varying upon the supplied image type
	public		$DecodingFunction	=  false ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Constructor - Builds an image from the supplied data.
	
	    PROTOTYPE
	        $image	=  new  PdfInlinedImage ( $image_data, $width, $height, $bits_per_component, $color_scheme ) ;
	
	    DESCRIPTION
	        Builds an image from the supplied data. Checks that the image flags are supported.
	
	    PARAMETERS
	        $image_data (string) -
	                Uncompressed image data.

		$width (integer) -
			Image width, in pixels.

		$height (integer) -
			Image height, in pixels.

		$bits_per_components (integer) -
			Number of bits per color component.

		$color_scheme (integer) - 
			One of the COLOR_SCHEME_* constants, specifying the initial data format.
	
	    NOTES
	        Processed images are always converted to JPEG format.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $image_data, $width, $height, $bits_per_component, $color_scheme )
	   {
		$this -> Width			=  $width ;
		$this -> Height			=  $height ;
		$this -> BitsPerComponent	=  $bits_per_component ;
		$this -> ColorScheme		=  $color_scheme ;

		// Check that we have a decoding function for the supplied parameters
		if  ( isset ( self::$Decoders [ $color_scheme ] ) )
		   {
			if  ( isset ( self::$Decoders [ $color_scheme ] [ $bits_per_component ] ) )
				$this -> DecodingFunction	=  self::$Decoders [ $color_scheme ] [ $bits_per_component ] ;
			else
				error ( new PdfToTextDecodingException ( "No decoding function has been implemented for image objects having the " .
						self::$DecoderNames [ $color_scheme ] . " color scheme with $bits_per_component bits per color component." ) ) ;
		    }
		else
			error ( new PdfToTextDecodingException ( "Unknown color scheme $color_scheme." ) ) ;

		parent::__construct ( $image_data ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        CreateInstance - Creates an appropriate instance of a PdfImage class.
	
	    PROTOTYPE
	        $image	=  PdfInlinedImage ( $stream_data, $object_data ) ;
	
	    DESCRIPTION
	        Creates an instance of either :
		- A PdfJpegImage class, if the image specifications in $object_data indicate that the compressed stream
		  contents are only JPEG data
		- A PdfInlinedImage class, if the image specifications state that the compressed stream data contain
		  only color values.

		The class currently supports (in $stream_data) :
		- Pure JPEG contents
		- RGB values
		- CMYK values
		- Gray scale values (in the current version, the resulting image does not correctly reproduce the 
		  initial colors, if interpolation is to be used).
	
	    PARAMETERS
	        $stream_data (string) -
	                Compressed image data.

		$object_data (string) -
			Object containing the stream data.
	
	    RETURN VALUE
	        Returns :
		- A PdfJpegImage object, if the stream data contains only pure JPEG contents
		- A PdfInlinedImage object, in other cases.
		- False if the supplied image data is not currently supported.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  CreateInstance ( $stream_data, $object_data, $autosave )
	   {
		// Remove stream data from the supplied object data, to speed up the searches below
		$index		=  strpos ( $object_data, 'stream' ) ;

		if  ( $index  !==   false )
			$object_data	=  substr ( $object_data, 0, $index ) ;

		// Uncompress stream data
		$image_data	=  gzuncompress ( $stream_data ) ;

		// The /DCTDecode flag indicates JPEG contents - returns a PdfJpegImage object
		if  ( stripos ( $object_data, '/DCTDecode' ) )
			return ( new PdfJpegImage ( $image_data, $autosave ) ) ;

		// Get the image width & height
		$match		=  null ;
		preg_match ( '#/Width \s+ (?P<value> \d+)#ix', $object_data, $match ) ;
		$width		=  ( integer ) $match [ 'value' ] ;

		$match		=  null ;
		preg_match ( '#/Height \s+ (?P<value> \d+)#ix', $object_data, $match ) ;
		$height		=  ( integer ) $match [ 'value' ] ;

		// Get the number of bits per color component
		$match		=  null ;
		preg_match ( '#/BitsPerComponent \s+ (?P<value> \d+)#ix', $object_data, $match ) ;
		$bits_per_component	=  ( integer ) $match [ 'value' ] ;

		// Get the target color space
		// Sometimes, this refers to an object in the PDF file, which can also be embedded in a compound object
		// We don't handle such cases for now
		$match		=  null ;
		preg_match ( '#/ColorSpace \s* / (?P<value> \w+)#ix', $object_data, $match ) ;

		if  ( ! isset ( $match [ 'value' ] ) )
			return ( false ) ;

		$color_space_name	=  $match [ 'value' ] ;

		// Check that we are able to handle the specified color space
		switch ( strtolower ( $color_space_name ) )
		   {
			case	'devicergb' :
				$color_space	=  self::COLOR_SCHEME_RGB ;
				break ;

			case	'devicegray' :
				$color_space	=  self::COLOR_SCHEME_GRAY ;
				break ;

			case	'devicecmyk' :
				$color_space	=  self::COLOR_SCHEME_CMYK ;
				break ;

			default :
				if  ( PdfToText::$DEBUG )
					warning ( new PdfToTextDecodingException ( "Unsupported color space \"$color_space_name\"." ) ) ;

				return ( false ) ;
		    }

		// Also check that we can handle the specified number of bits per component
		switch ( $bits_per_component )
		   {
			case	8 :
				break ;

			default :
				if  ( PdfToText::$DEBUG )
					warning ( new PdfToTextDecodingException ( "Unsupported bits per component : $bits_per_component." ) ) ;

				return ( false ) ;
		    }

		// All done, return a PdfInlinedImage object
		return ( new PdfInlinedImage ( $image_data, $width, $height, $bits_per_component, $color_space ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        CreateImageResource - Creates the image resource.
	
	    PROTOTYPE
	        $resource	=  $image -> CreateImageResource ( $image_data ) ;
	
	    DESCRIPTION
	        Creates a GD image according to the supplied image data, and the parameters supplied to the class
		constructor.
	
	    PARAMETERS
	        $image_data (string) -
	                Image to be decoded.
	
	    RETURN VALUE
	        Returns a GD graphics resource in true color, or false if there is currently no implemented decoding
		function for this kind of images.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  CreateImageResource ( $image_data ) 
	   {
		$decoder	=  $this -> DecodingFunction ;

		if  ( $decoder )
			return ( $this -> $decoder ( $image_data ) ) ;
		else
			return ( false ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Decoding functions.

	 *-------------------------------------------------------------------------------------------------------------*/

	// __decode_rgb8 -
	//	Decodes image data consisting of 8-bits RGB values (one byte for each color component).
	private function  __decode_rgb8 ( $data )
	   {
		$data_length	=  strlen ( $data ) ;
		$colors		=  array ( ) ;
		$width		=  $this -> Width ;
		$height		=  $this -> Height ;
		$image		=  imagecreatetruecolor ( $width, $height ) ;

		for  ( $i = 0, $pixel_x = 0, $pixel_y = 0 ; $i + 3  <=  $data_length ; $i += 3, $pixel_x ++ )
		   {
			$red	=  ord ( $data [$i] ) ;
			$green	=  ord ( $data [$i+1] ) ;
			$blue	=  ord ( $data [$i+2] ) ;

			$color	=  ( $red  <<  16 ) | ( $green  <<  8 )  | ( $blue ) ;

			if  ( isset ( $colors [ $color ] ) )
				$pixel_color	=  $colors [ $color ] ;
			else
			   {
				$pixel_color		=  imagecolorallocate ( $image, $red, $green, $blue ) ;
				$colors [ $color ]	=  $pixel_color ;
			    }

			if  ( $pixel_x  >=  $width )
			   {
				$pixel_x	=  0 ;
				$pixel_y ++ ;
			    }

			imagesetpixel ( $image, $pixel_x, $pixel_y, $pixel_color ) ;
		    }

		return ( $image ) ;
	    }


	// __decode_cmyk8 -
	//	Decodes image data consisting of 8-bits CMYK values (one byte for each color component).
	private function  __decode_cmyk8 ( $data )
	   {
		$data_length	=  strlen ( $data ) ;
		$colors		=  array ( ) ;
		$width		=  $this -> Width ;
		$height		=  $this -> Height ;
		$image		=  imagecreatetruecolor ( $width, $height ) ;

		for  ( $i = 0, $pixel_x = 0, $pixel_y = 0 ; $i + 4  <=  $data_length ; $i += 4, $pixel_x ++ )
		   {
			$cyan		=  ord ( $data [$i] ) ;
			$magenta	=  ord ( $data [$i+1] ) ;
			$yellow		=  ord ( $data [$i+2] ) ;
			$black		=  ord ( $data [$i+3] ) ;

			$color	=  ( $cyan  <<  24 ) | ( $magenta  <<  16 ) | ( $yellow  << 8 ) | ( $black ) ;

			if  ( isset ( $colors [ $color ] ) )
				$pixel_color	=  $colors [ $color ] ;
			else
			   {
				$rgb			=  $this -> __convert_cmyk_to_rgb ( $cyan, $magenta, $yellow, $black ) ;
				$pixel_color		=  imagecolorallocate ( $image, $rgb [0], $rgb [1], $rgb [2] ) ;
				$colors [ $color ]	=  $pixel_color ;
			    }

			if  ( $pixel_x  >=  $width )
			   {
				$pixel_x	=  0 ;
				$pixel_y ++ ;
			    }

			imagesetpixel ( $image, $pixel_x, $pixel_y, $pixel_color ) ;
		    }

		return ( $image ) ;
	    }


	// __decode_gray8 -
	//	Decodes image data consisting of 8-bits gray values.
	private function  __decode_gray8 ( $data )
	   {
		$data_length	=  strlen ( $data ) ;
		$colors		=  array ( ) ;
		$width		=  $this -> Width ;
		$height		=  $this -> Height ;
		$image		=  imagecreatetruecolor ( $width, $height ) ;

		for  ( $i = 0, $pixel_x = 0, $pixel_y = 0 ; $i  <  $data_length ; $i ++, $pixel_x ++ )
		   {
			$color	=  ord ( $data [$i] ) ;

			if  ( isset ( $colors [ $color ] ) )
				$pixel_color	=  $colors [ $color ] ;
			else
			   {
				$pixel_color		=  imagecolorallocate ( $image, $color, $color, $color ) ;
				$colors [ $color ]	=  $pixel_color ;
			    }

			if  ( $pixel_x  >=  $width )
			   {
				$pixel_x	=  0 ;
				$pixel_y ++ ;
			    }

			imagesetpixel ( $image, $pixel_x, $pixel_y, $pixel_color ) ;
		    }

		return ( $image ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Support functions.

	 *-------------------------------------------------------------------------------------------------------------*/

	// __convert_cmyk_to_rgb -
	//	Converts CMYK color value to RGB.
	private function  __convert_cmyk_to_rgb ( $C, $M, $Y, $K )
	   {
		if  ( $C  >  1  ||  $M  >  1  ||  $Y  >  1  ||  $K  >  1 )
		   {
			$C /= 100.0 ;
			$M /= 100.0 ;
			$Y /= 100.0 ;
			$K /= 100.0 ;
		    }

   		$R 	=  ( 1 - $C * ( 1 - $K ) - $K ) * 256 ;
   		$G 	=  ( 1 - $M * ( 1 - $K ) - $K ) * 256 ;
   		$B 	=  ( 1 - $Y * ( 1 - $K ) - $K ) * 256 ;

		$result =  array ( round ( $R ), round ( $G ), round ( $B ) ) ;

		return ( $result ) ;
  	    }
    }


/*==============================================================================================================

    class PdfFaxImage -
        Handles encoded CCITT Fax images.

  ==============================================================================================================*/
class  PdfFaxImage		extends  PdfImage 
   {
	public function  __construct ( $image_data )
	   {
		parent::__construct ( $image_data ) ;
	    }


	protected function  CreateImageResource ( $image_data )
	   {
		warning ( new PdfToTextDecodingException ( "Decoding of CCITT Fax image format is not yet implemented." ) ) ;
		//return ( imagecreatefromstring ( $image_data ) ) ;
	    }
    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                      ENCRYPTION MANAGEMENT                                       ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    class EncryptionData -
        Holds encryption data and allows for decryption.

  ==============================================================================================================*/
class  PdfEncryptionData		extends  PdfObjectBase
   {
	// Encryption modes 
	const		PDFMODE_UNKNOWN				=  0 ;
	const		PDFMODE_STANDARD			=  1 ;

	// Encryption algorithms
	const		PDFCRYPT_ALGORITHM_RC4			=  0 ;
	const		PDFCRYPT_ALGORITHM_AES			=  1 ;
	const		PDFCRYPT_ALGORITHM_AES256		=  2 ;

	// A 32-bytes hardcoded padding used when computing encryption keys
	const		PDF_ENCRYPTION_PADDING			=  "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A" ;

	// Permission bits for encrypted files. Comments come from the PDF specification
	const		PDFPERM_PRINT				=  0x0004 ;		// bit 3 :
											//	(Revision 2) Print the document.
											//	(Revision 3 or greater) Print the document (possibly not at the highest quality level, 
											//	depending on whether bit 12 is also set).
	const		PDFPERM_MODIFY				=  0x0008 ;		// bit 4 :
											//	Modify the contents of the document by operations other than those controlled by bits 6, 9, and 11.
	const		PDFPERM_COPY				=  0x0010 ;		// bit 5 :
											//	(Revision 2) Copy or otherwise extract text and graphics from the document, including extracting text 
											//	and graphics (in support of accessibility to users with disabilities or for other purposes).
											//	(Revision 3 or greater) Copy or otherwise extract text and graphics from the document by operations  
											//	other than that controlled by bit 10.
	const		PDFPERM_MODIFY_EXTRA			=  0x0020 ;		// bit 6 :
											//	Add or modify text annotations, fill in interactive form fields, and, if bit 4 is also set, 
											//	create or modify interactive form fields (including signature fields).
	const		PDFPERM_FILL_FORM			=  0x0100 ;		// bit 9 :
											//	(Revision 3 or greater) Fill in existing interactive form fields (including signature fields), 
											//	even if bit 6 is clear.
	const		PDFPERM_EXTRACT				=  0x0200 ;		// bit 10 :
											//	(Revision 3 or greater) Fill in existing interactive form fields (including signature fields), 
											//	even if bit 6 is clear.
	const		PDFPERM_ASSEMBLE			=  0x0400 ;		// bit 11 :
											//	(Revision 3 or greater) Assemble the document (insert, rotate, or delete pages and create bookmarks 
											//	or thumbnail images), even if bit 4 is clear.
	const		PDFPERM_HIGH_QUALITY_PRINT		=  0x0800 ;		// bit 12 :
											//	(Revision 3 or greater) Print the document to a representation from which a faithful digital copy of 
											//	the PDF content could be generated. When this bit is clear (and bit 3 is set), printing is limited to 
											//	a low-level representation of the appearance, possibly of degraded quality. 

	public		$FileId ;							// File ID, as specified by the /ID flag
	public		$ObjectId ;							// Object id and text contents
	private		$ObjectData ;
	public		$Mode ;								// Encryption mode - currently, only the "Standard" keyword is accepted
	public		$EncryptionAlgorithm ;						// Encryption algorithm - one of the PDFCRYPT_* constants
	public		$AlgorithmVersion,						// Encryption algorithm version & revision
			$AlgorithmRevision ;
	public		$Flags ;							// Protection flags, when an owner password has been specified - one of the PDFPERM_* constants
	public		$KeyLength ;							// Encryption key length
	public		$UserKey,							// User and owner password keys
			$OwnerKey ;
	public		$UserEncryptionString,						// Not sure yet of the real usage of these ones
			$OwnerEncryptionString ;
	public		$EncryptMetadata ;						// True if metadata is also encrypted
	public		$FileKeyLength ;						// Key length / 5

	protected	$Decrypter ;							// Decrypter object

	private		$UnsupportedEncryptionAlgorithm		=  false ;		// True if the encryption algorithm used in the PDF file is not yet supported


	/**************************************************************************************************************
	
	    NAME
	        Constructor
	
	    PROTOTYPE
	        obj	=  new  PdfEncryptionData ( $mode, $object_id, $object_data ) ;

	    DESCRIPTION
		Creates an instance of a PdfEncryptionData class, using the information parsed from the supplied object
		data.

	    PARAMETERS
		$mode (integer) -
			One of the PDFMODE_* constants.

		$object_id (integer) -
			Id of the object containing enryption parameters.

		$object_data (string) -
			Encryption parameters.
	
	    AUTHOR
	        Christian Vigh, 03/2017.
	
	    HISTORY
	    [Version : 1.0]	[Date : 2017-03-14]     [Author : CV]
	        Initial version.
	
	 **************************************************************************************************************/
	public function  __construct ( $file_id, $mode, $object_id, $object_data )
	   {
		$this -> FileId			=  $file_id ;
		$this -> ObjectId		=  $object_id ;
		$this -> ObjectData		=  $object_data ;
		$this -> Mode			=  $mode ;

		// Encryption algorithm version & revision
		preg_match ( '#/V \s+ (?P<value> \d+)#ix', $object_data, $algorithm_match ) ;
		$this -> AlgorithmVersion	=  ( integer ) $algorithm_match [ 'value' ] ;

		preg_match ( '#/R \s+ (?P<value> \d+)#ix', $object_data, $algorithm_revision_match ) ;
		$this -> AlgorithmRevision	=  ( integer ) $algorithm_revision_match [ 'value' ] ;

		// Encryption flags
		preg_match ( '#/P \s+ (?P<value> \-? \d+)#ix', $object_data, $flags_match ) ;
		$this -> Flags			=  ( integer) $flags_match [ 'value' ] ;

		// Key length (40 bits, if not specified)
		if  ( preg_match ( '#/Length \s+ (?P<value> \d+)#ix', $object_data, $key_length_match ) )
			$this -> KeyLength	=  $key_length_match [ 'value' ] ;
		else 
			$this -> KeyLength	=  40 ;

		// Owner and user passwords
		$this -> UserKey		=  $this -> GetStringParameter ( '/U', $object_data ) ;
		$this -> OwnerKey		=  $this -> GetStringParameter ( '/O', $object_data ) ;

		// Owner and user encryption strings
		$this -> UserEncryptionString	=  $this -> GetStringParameter ( '/UE', $object_data ) ;
		$this -> OwnerEncryptionString	=  $this -> GetStringParameter ( '/OE', $object_data ) ;

		// EncryptMetadata flag
		if  ( preg_match ( '# /EncryptMetadata (?P<value> (true) | (1) | (false) | (0) )#imsx', $object_data, $encryption_match ) )
		   {
			if  ( ! strcasecmp ( $encryption_match [ 'value' ], 'true' )  ||  ! strcasecmp ( $encryption_match [ 'value' ], 'false' ) )
				$this -> EncryptMetadata		=  true ;
			else
				$this -> EncryptMetadata		=  false ;
		    }
		else
			$this -> EncryptMetadata	=  false ;

		// Now, try to determine the encryption algorithm to be used
		$user_key_length		=  strlen ( $this -> UserKey ) ;
		$owner_key_length		=  strlen ( $this -> OwnerKey ) ;
		$user_encryption_string_length	=  strlen ( $this -> UserEncryptionString ) ;
		$owner_encryption_string_length	=  strlen ( $this -> OwnerEncryptionString ) ;

		$error_unhandled_version	=  false ;
		$error_unhandled_revision	=  false ;

		switch  ( $this -> AlgorithmVersion )
		   {
			case	1 :
				switch  ( $this -> AlgorithmRevision )
				   {
					case	2 :
						if  ( $user_key_length  !=  32  &&  $owner_key_length  !=  32 )
						   {
							if  ( PdfToText::$DEBUG )
								error ( new PdfToTextDecryptionException ( "Invalid user and/or owner key length ($user_key_length/$owner_key_length)", $object_id ) ) ;
						    }

						$this -> EncryptionAlgorithm	=  self::PDFCRYPT_ALGORITHM_RC4 ;
						$this -> FileKeyLength		=  5 ;
						break ;

					default :
						$error_unhandled_revision	=  true ;
				    }
				break ;

			default :
				$error_unhandled_version	=  true ;
		    }

		// Report unsupported versions/revisions
		if  ( $error_unhandled_version  ||  $error_unhandled_revision )
		   {
			if  ( PdfToText::$DEBUG )
				error ( new PdfToTextDecryptionException ( "Unsupported encryption algorithm version {$this -> AlgorithmVersion} revision {$this -> AlgorithmRevision}.", 
						$object_id ) ) ;

			$this -> UnSupportedEncryptionAlgorithm		=  true ;

			return ;
		    }

		// Build the object key
		$this -> Decrypter		=  PdfDecryptionAlgorithm::GetInstance ( $this ) ;

		if  ( $this -> Decrypter  ===  false )
		   {
			if  ( PdfToText::$DEBUG )
				warning ( new PdfToTextDecryptionException ( "Unsupported encryption algorithm #{$this -> EncryptionAlgorithm}, " .
						"version {$this -> AlgorithmVersion} revision {$this -> AlgorithmRevision}.", 
						$object_id ) ) ;

			$this -> UnsupportedEncryptionAlgorithm		=  true ;

			return ;
		    }
		//dump ( $this ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetInstance - Creates an instance of a PdfEncryptionData object.
	
	    PROTOTYPE
	        $obj		=  PdfEncryptionData::GetInstance ( $object_id, $object_data ) ;
	
	    DESCRIPTION
	        Returns an instance of encryption data
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  GetInstance ( $file_id, $object_id, $object_data )
	   {
		// Encryption mode
		if  ( ! preg_match ( '#/Filter \s* / (?P<mode> \w+)#ix', $object_data, $object_data_match ) )
			return  (false ) ;

		switch ( strtolower ( $object_data_match [ 'mode' ] ) )
		   {
			case	'standard' :
				$mode		=  self::PDFMODE_STANDARD ;
				break ;

			default :
				if  ( self::$DEBUG  >  1 )
					error ( new PdfToTextDecodingException ( "Unhandled encryption mode '{$object_data [ 'mode' ]}'", $object_id ) ) ;

				return ( false ) ;

		    }

		// Basic checks have been performed, return an instance of encryption data
		return ( new PdfEncryptionData ( $file_id, $mode, $object_id, $object_data ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Decrypt - Decrypts object data.
	
	    PROTOTYPE
	        $data		=  $this -> Decrypt ( $object_id, $object_data ) ;
	
	    DESCRIPTION
	        Decrypts object data, when the PDF file is password-protected.
	
	    PARAMETERS
	        $object_id (integer) -
	                Pdf object number.

		$object_data (string) -
			Object data.
		
	    RETURN VALUE
	        Returns the decrypted object data, or false if the encrypted object could not be decrypted.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  Decrypt ( $object_id, $object_data ) 
	   {
		if  ( $this -> UnsupportedEncryptionAlgorithm )
			return ( false ) ;

		return ( false ) ;
		//return ( $this -> Decrypter -> Decrypt ( $object_data ) ) ;
		//return ( "BT (coucou)Tj ET" ) ;
	    }
    }


/*==============================================================================================================

    class PdfDecryptionAlgorithm -
        Base class for algorithm decrypters.

  ==============================================================================================================*/
abstract class	PdfDecryptionAlgorithm		//extends  Object
   {
	protected		$EncryptionData ;
	protected		$ObjectKey ;
	protected		$ObjectKeyBytes ;
	protected		$ObjectKeyLength ;


	public function  __construct ( $encryption_data )
	   {
		$this -> EncryptionData		=  $encryption_data ;

		$objkey		=  '' ;

		for  ( $i = 0 ; $i  <  $this -> EncryptionData -> FileKeyLength ; $i ++ )
			$objkey 	.=  $this -> EncryptionData -> FileId [$i] ;

		$objkey 			.=  chr ( ( $this -> EncryptionData -> ObjectId ) & 0xFF ) ;
		$objkey 			.=  chr ( ( $this -> EncryptionData -> ObjectId  >>   8 )  &  0xFF ) ;
		$objkey 			.=  chr ( ( $this -> EncryptionData -> ObjectId  >>  16 )  &  0xFF ) ;
		$objkey 			.=  chr ( 0 ) ;		// obj generation number & 0xFF
		$objkey 			.=  chr ( 0 ) ;		// obj generation number >> 8  &  0xFF
			
		$md5				=  md5 ( $objkey, true ) ;
		$this -> ObjectKey		=  $md5 ;
		$this -> ObjectKeyLength	=  16 ;

		$this -> ObjectKeyBytes		=  array ( ) ;

		for  ( $i = 0 ; $i  <  $this -> ObjectKeyLength ; $i ++ )
			$this -> ObjectKeyBytes  []	=  ord ( $this -> ObjectKey [$i] ) ;
	    }


	public static function  GetInstance  ( $encryption_data )
	   {
		switch  ( $encryption_data -> EncryptionAlgorithm ) 
		   {
			case	PdfEncryptionData::PDFCRYPT_ALGORITHM_RC4 :
				return ( new PdfRC4DecryptionAlgorithm ( $encryption_data ) ) ;

			default :
				return ( false ) ;
		    }
	    }


	abstract public function  Reset		( ) ;
	abstract public function  Decrypt	( $data ) ;

    }


/*==============================================================================================================

    class PdfRC4DecryptionAlgorithm -
        A decrypter class for RC4 encoding.

  ==============================================================================================================*/
class	PdfRC4DecryptionAlgorithm		extends  PdfDecryptionAlgorithm
   {
	private	static		$InitialState		=  false ;
	protected		$State ;


	public function  __construct ( $encryption_data )
	   {
		parent::__construct ( $encryption_data ) ;

		if  ( self::$InitialState  ===  false )
			self::$InitialState	=  range ( 0, 255 ) ;
	    }


	public function  Reset ( )
	   {
		$this -> State		=  self::$InitialState ;
		$index1			=
		$index2			=  0 ;

		for  ( $i = 0 ; $i  <  256 ; $i ++ )
		   {
			$index2		=  ( $this -> ObjectKeyBytes [ $index1 ] + $this -> State [$i] + $index2 )  &  0xFF ;

			// Swap elements $index2 and $i from $State
			$x				=  $this -> State [$i] ;
			$this -> State [$i]		=  $this -> State [ $index2 ] ;
			$this -> State [ $index2 ]	=  $x ;

			$index1  =  ( $index1 + 1 ) % $this -> ObjectKeyLength ;
		    }
	    }


	public function  Decrypt ( $data )
	   {
		$this -> Reset ( ) ;
		$length		=  strlen ( $data ) ;
		$x		=  0 ;
		$y		=  0 ;
		$result		=  '' ;

		for  ( $i = 0 ; $i  <  $length ; $i ++ )
		   {
			$ord	=  ord ( $data [$i] ) ;
			$x	=  ( $x + 1 ) & 0xFF ;
			$y	=  ( $this -> State [$x] + $y ) & 0xFF ;

			$tx	=  $this -> State [$x] ;
			$ty	=  $this -> State [$y] ;

			$this -> State [$x]	=  $ty ;
			$this -> State [$y]	=  $tx ;

			$new_ord		=  $ord ^ $this -> State [ ( $tx + $ty ) & 0xFF ] ;
			$result		.=  chr ( $new_ord ) ;
		    }

		return ( $result ) ;
	    }
    }

    /*
static Guchar rc4DecryptByte(Guchar *state, Guchar *x, Guchar *y, Guchar c) {
  Guchar x1, y1, tx, ty;

  x1 = *x = (*x + 1) % 256;
  y1 = *y = (state[*x] + *y) % 256;
  tx = state[x1];
  ty = state[y1];
  state[x1] = ty;
  state[y1] = tx;
  return c ^ state[(tx + ty) % 256];
}
*/


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                       FORM DATA MANAGEMENT                                       ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/


/*==============================================================================================================

    class PdfToTextFormDefinitions -
        Analyzes a template XML file that describes PDF form data and maps PDF field names to human-readable
	names.
	The GetFormData() returns an object containing the mapped properties with their respective values.

  ==============================================================================================================*/
class  PdftoTextFormDefinitions		// extends		Object
					implements	ArrayAccess, Countable, IteratorAggregate
   {
	static private	$ClassDefinitionCount	=  0 ;

	// Class name, as specified in the XML template
	protected	$ClassName ;
	// Form definitions (a template may contain several versions of the same for definition)
	protected	$Definitions ;
	// Form definitions coming from the PDF file
	protected	$PdfDefinitions ;


	/*--------------------------------------------------------------------------------------------------------------

	    Constructor -
		Parses the supplied XML template.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $xml_data, $pdf_xml_data )
	   {
		// Get PDF XML form data definitions
		$this -> __get_pdf_form_definitions ( $pdf_xml_data ) ;

		// Create XML data from scratch, if none specified
		if  ( ! $xml_data  )
			$xml_data	=  $this -> __create_default_xml_data ( $this -> PdfDefinitions ) ;

		// Decode XML the hard way, without XSD
		$xml		=  simplexml_load_string ( $xml_data ) ;
		$root_entry	=  $xml -> getName ( ) ;
		$definitions	=  array ( ) ;
		$class_name	=  "PdfFormData" ;

		if  ( strcasecmp ( $root_entry, "forms" ) ) 
			error ( new PdfToTextFormException ( "Root entry must be <forms>, <$root_entry> was found." ) ) ;

		// Get the attribute values of the <forms> tag
		foreach  ( $xml -> attributes ( )  as  $attribute_name => $attribute_value )
		   {
			switch  ( strtolower ( $attribute_name ) )
			   {
				case	'class' :
					$class_name	=  ( string ) $attribute_value ;

					if  ( class_exists ( $class_name, false ) )
						error ( new PdfToTextFormException ( "Class \"$class_name\" specified in XML template already exists." ) ) ;

					break ;

				default :
					error ( new PdfToTextFormException ( "Invalid attribute \"$attribute_name\" in <forms> tag." ) ) ;
			    }
		    }

		// Don't know if it will be useful, but try to avoid class name collisions by appending a sequential number if necessary
		if  ( class_exists ( $class_name, false ) )
		   {
			self::$ClassDefinitionCount ++ ;
			$class_name	.=  '_' . self::$ClassDefinitionCount ;
		    }

		// Loop through each child <form> entry
		foreach  ( $xml -> children ( )  as  $child )
		   {
			$child_name	=  $child -> getName ( ) ;

			switch ( strtolower ( $child_name ) )
			   {
				case	'form' :
					$definitions []		=  new PdfToTextFormDefinition ( $class_name, $child, $this -> PdfDefinitions ) ;
					break ;

				default :
					error ( new PdfToTextFormException ( "Invalid tag <$child_name>." ) ) ;
			    }
		    }

		// Ensure that there is at least one form definition
		if  ( ! count ( $definitions ) )
			error ( new PdfToTextFormException ( "No <form> definition found." ) ) ;

		// Save to properties
		$this -> ClassName		=  $class_name ;
		$this -> Definitions		=  $definitions ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

		Internal methods.

	 *-------------------------------------------------------------------------------------------------------------*/

	// __get_pdf_form_definitions -
	//	Retrieves the form field definitions coming from the PDF file.
	private function  __get_pdf_form_definitions ( $pdf_data )
	   {
		preg_match_all ( '#(?P<field> <field .*? </field \s* >)#imsx', $pdf_data, $matches ) ;
		
		foreach  ( $matches [ 'field' ]  as  $field )
		   {
			$xml_field	=  simplexml_load_string ( $field ) ;

			foreach  ( $xml_field -> attributes ( )  as  $attribute_name => $attribute_value )
			   {
				switch  ( strtolower ( $attribute_name ) )
				   {
					case	'name' :
						$field_name		=  ( string ) $attribute_value ;

						if  ( isset ( $this -> PdfDefinitions [ $field_name ] ) )
							$this -> PdfDefinitions [ $field_name ] [ 'occurrences' ] ++ ;
						else
						   {
							$this -> PdfDefinitions [ $field_name ]		=  array
							   (
								'name'		=>  $field_name,
								'occurrences'	=>  1
							    ) ;
						    }

						break ;
				    }
			    }
		    }
	    }


	// __create_default_xml_data -
	//	When no XML template has been specified, creates a default one based of the form definitions located in the PDF file.
	private function  __create_default_xml_data ( $pdf_definitions )
	   {
		$result		=  "<forms>" . PHP_EOL .
				   "\t<form version=\"1.0\">" . PHP_EOL ;

		foreach  ( $pdf_definitions  as  $name => $field )
		   {
			$name		 =  str_replace ( '-', '_', $name ) ;		// Just in case of
			$result		.=  "\t\t<field name=\"$name\" form-field=\"$name\" type=\"string\"/>" . PHP_EOL ;
		    }

		$result		.=  "\t</form>" . PHP_EOL .
				    "</forms>" . PHP_EOL ;

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------

		Interfaces implementations to retrieve form definitions.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( count ( $this - Definitions ) ) ; }


	public function  getIterator ( )
	   { return ( new ArrayIterator ( $this -> Definitions ) ) ; }


	public function  offsetExists ( $offset )
	   { return ( $offset  >=  0  &&  $offset  <  count ( $this -> Definitions ) ) ; }


	public function  offsetGet ( $offset )
	   { return ( $this -> Definitions [ $offset ] ) ; }


	public function  offsetSet ( $offset, $value ) 
	   { error ( new PdfToTextException ( "Unsupported operation." ) ) ; }


	public function  offsetunset ( $offset ) 
	   { error ( new PdfToTextException ( "Unsupported operation." ) ) ; }
    }	


/*==============================================================================================================

    class PdfToTextFormDefinition -
        Holds the description of a form inside a form XML template.

  ==============================================================================================================*/
class  PdfToTextFormDefinition		// extends  Object
   {
	// Class of the object returned by GetFormData( )
	public		$ClassName ;

	// Form version
	public		$Version ;

	// Field definitions
	public		$FieldDefinitions		=  array ( ) ;

	// Field groups (ie, fields that are the results of the concatenation of several form fields)
	public		$Groups				=  array ( ) ;

	// Pdf field definitions
	public		$PdfDefinitions ;
	
	// Class definition in PHP, whose instance will be returned by GetFormData()
	private		$ClassDefinition		=  false ;

	// Direct access to field definitions either through their template name or PDF name
	private		$FieldDefinitionsByName		=  array ( ) ;
	private		$FieldDefinitionsByPdfName	=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Analyze the contents of an XML template form definition.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $class_name, $form_definition, $pdf_definitions )
	   {
		$this -> ClassName		=  $class_name ;
		$this -> PdfDefinitions		=  $pdf_definitions ;
		$field_count			=  0 ;

		// Get <form> tag attributes
		foreach  ( $form_definition -> attributes ( )  as  $attribute_name => $attribute_value )
		   {
			switch ( strtolower ( $attribute_name ) ) 
			   {
				case	'version' :
					$this -> Version	=  ( string ) $attribute_value ;
					break ;

				default :
					error ( new PdfToTextFormException ( "Invalid attribute \"$attribute_name\" in <form> tag." ) ) ;
			    }
		    }

		// Loop through subtags
		foreach  ( $form_definition -> children ( )  as  $child )
		   {
			$tag_name	=  $child -> getName ( ) ;

			// Check subtags
			switch  ( strtolower ( $tag_name ) )
			   {
				// <group> :
				//	A group is used to create a property that is the concatenation of several existing properties.
				case	'group' :
					$fields		=  array ( ) ;
					$separator	=  '' ;
					$name		=  false ;

					// Loop through attribute names
					foreach  ( $child -> attributes ( )  as  $attribute_name => $attribute_value )
					   {
						switch  ( $attribute_name )
						   {
							// "name" attribute" :
							//	The name of the property, as it will appear in the output object.
							case	'name' :
								$name		=  PdfToTextObjectBase::ValidatePhpName ( ( string ) $attribute_value ) ;
								break ;

							// "separator" attribute :
							//	Separator to be used when concatenating the underlying properties.
							case	'separator' :
								$separator	=  ( string ) $attribute_value ;
								break ;

							// "fields" :
							//	A list of comma-separated field names, whose values will be concatenated together
							//	using the specified separator.
							case	'fields' :
								$items		=  explode ( ',', ( string ) $attribute_value ) ;

								if  ( ! count ( $items ) )
									error ( new PdfToTextFormException ( "Empty \"fields\" attribute in <group> tag." ) ) ;

								foreach  ( $items  as  $item )
									$fields []	=  PdfToTextObjectBase::ValidatePhpName ( $item ) ;

								break ;

							// Other attribute names : not allowed
							default :
								error ( new PdfToTextFormException ( "Invalid attribute \"$attribute_name\" in <group> tag." ) ) ;
						    }
					    }

					// Check that at least one field has been specified
					if  ( ! count ( $fields ) )
						error ( new PdfToTextFormException ( "Empty \"fields\" attribute in <group> tag." ) ) ;

					// Check that the mandatory property name has been specified
					if  ( ! $name )
						error ( new PdfToTextFormException ( "The \"name\" attribute is mandatory in <group> tag." ) ) ;

					// Add this new grouped property to the list of existing groups
					$this -> Groups []	=  array
					   (
						'name'		=>  $name,
						'separator'	=>  $separator,
						'fields'	=>  $fields
					    ) ;

					break ;

				// <field> :
				//	Field definition.
				case	'field' :
					$field_def							=  new PdfToTextFormFieldDefinition ( $child ) ;
					$this -> FieldDefinitions []					=  $field_def ;
					$this -> FieldDefinitionsByName [ $field_def -> Name ]		=
					$this -> FieldDefinitionsByPdfName [ $field_def -> PdfName ]	=  $field_count ;
					$field_count ++ ;
					break ;

				// Don't allow other attribute names
				default :
					error ( new PdfToTextFormException ( "Invalid tag <$tag_name> in <form> definition." ) ) ;
			    }
		    }

		// Check that everything is ok (ie, that there is no duplicate fields)
		$this -> __paranoid_checks ( ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetClassDefinition - Returns the class definition for the urrent form.
	
	    PROTOTYPE
	        $def	=   $form_def -> GetClassDefinition ( ) ;
	
	    DESCRIPTION
	        Returns a string containing the PHP class definition that will contain the properties defined in the XML
		form template.
	
	    RETURN VALUE
	        Returns a string containing the PHP class definition for the current form.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetClassDefinition ( )
	   {
		// Return the existing definition, if this method has been called more than once
		if  ( $this -> ClassDefinition )
			return ( $this -> ClassDefinition ) ;

		$class_def	=  "// Class " . $this -> ClassName . " : " . $this -> Version . PHP_EOL .
				   "class {$this -> ClassName}\t\textends PdfToTextFormData" . PHP_EOL .
				   "   {" . PHP_EOL ;

		// Get the maximum width of constant and field names
		$max_width	=  0 ;

		foreach  ( $this -> FieldDefinitions  as  $def )
		   {
			$length1	=  strlen ( $def -> Name ) ;
			$length2	=  strlen ( $def -> PdfName ) ;

			if  ( $length1  >  $max_width  ||  $length2  >  $max_width )
				$max_width	=  max ( $length1, $length2 ) ;

			foreach  ( $def -> Constants  as  $constant )
			   {
				$length		=  strlen ( $constant [ 'name' ] ) ;

				if  ( $length  >  $max_width )
					$max_width	=  $length ;
			    }
		    }

		// First, write out the constant definitions 
		$all_constants	=  array ( ) ;

		foreach  ( $this -> FieldDefinitions  as  $def )
		   {
			foreach  ( $def -> Constants  as  $constant )
			   {
				$name	=  $constant [ 'name' ] ;
				$value	=  $constant [ 'value' ] ;

				if  ( isset ( $all_constants [ $name ] ) )
				   {
					if  ( $all_constants [ $name ]  !=  $value )
						error ( new PdfToTextFormException ( "Constant \"$name\" is defined more than once with different values." ) ) ;
				    }
				else
				   {
					$all_constants [ $name ]	 =  $value ;

					if  ( ! is_numeric ( $value ) )
						$value		=  '"' . addslashes ( $value ) . '"' ;

					$class_def			.=  "\tconst\t" . str_pad ( $name, $max_width, " ", STR_PAD_RIGHT ) . "\t = $value ; " . PHP_EOL ;
				    }
			    }
		    }

		$class_def	.=  PHP_EOL . PHP_EOL ;

		// Then write property definitions
		foreach  ( $this -> FieldDefinitions  as  $def )
		   {
			$class_def	.=  "\t/** @formdata */" . PHP_EOL .
					     "\tprotected\t\t\${$def -> Name} ;" . PHP_EOL ;
		    }

		$class_def	.=  PHP_EOL . PHP_EOL ;

		// And finally, grouped properties
		foreach  ( $this -> Groups  as  $group ) 
		   {
			$class_def	.=  "\t/**" . PHP_EOL .
					    "\t\t@formdata" . PHP_EOL .
					    "\t\t@group(" . implode ( ',', $group [ 'fields' ] ) . ')' . PHP_EOL .
					    "\t\t@separator(" . str_replace ( ')', '\)', $group [ 'separator' ] ) . ')' . PHP_EOL .
					    "\t */" . PHP_EOL .
					    "\tprotected\t\t\${$group [ 'name' ]} ;" . PHP_EOL .PHP_EOL ;
		    }

		// Constructor
		$class_def	.=  PHP_EOL . PHP_EOL .
				    "\t// Class constructor" . PHP_EOL .
				    "\tpublic function  __construct ( )" . PHP_EOL .
				    "\t   {" . PHP_EOL .
				    "\t\tparent::__construct ( ) ;" . PHP_EOL .
				    "\t    }" . PHP_EOL ;

		$class_def	.=  "    }" . PHP_EOL ;

		// Save the definition, if a second call occurs
		$this -> ClassDefinition	=  $class_def ;

		// All done, return
		return ( $class_def ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetFormData - Returns a form data object containing properties mapped to the form data.
	
	    PROTOTYPE
	        $object		=  $form_def -> GetFormData ( $fields ) ;
	
	    DESCRIPTION
	        Returns an object containing properties mapped to actual form data.
	
	    PARAMETERS
	        $fields (array) -
	                An associative array whoses keys are the PDF form field names, and values their values as stored
			in the PDF file.
	
	    RETURN VALUE
	        Returns an object of the class, as defined by the template specified to PdfToTextFormDefinitions
		class constructor.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetFormData ( $fields = array ( ) )
	   {
		if  ( ! class_exists ( $this -> ClassName, false ) )
		   {
			$class_def	=  $this -> GetClassDefinition ( ) ;
			eval ( $class_def ) ;
		    }

		$class_name	=  $this -> ClassName ;
		$object		=  new  $class_name ( ) ;

		foreach  ( $fields  as  $name => $value )
		   {
			if  ( isset ( $this -> FieldDefinitionsByPdfName [ $name ] ) )
			   {
				$property		=  $this -> FieldDefinitions [ $this -> FieldDefinitionsByPdfName [ $name ] ] -> Name ;
				$object -> $property	=  $this -> __process_field_value ( $value ) ;
			    }
		    }

		return ( $object ) ;
	    }


	// __process_field_values -
	//	Translates html entities and removes carriage returns (which are apparently used for multiline field) to 
	//	replace them with newlines.
	private function  __process_field_value ( $value )
	   {
		$value		=  html_entity_decode ( $value ) ;
		$result		=  '' ;

		for  ( $i = 0, $length = strlen ( $value ) ; $i  <  $length ; $i ++ )
		   {
			if  ( $value [$i]  !==  "\r" )
				$result		.=  $value [$i] ;
			else
			   {
				if  ( isset ( $value [ $i + 1 ] ) )
				   {
					if  ( $value [ $i + 1 ]  !==  "\n" )
						$result		.=  "\n" ;
				    }
				else
					$result		.=  "\n" ;
			    }
		    }

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetformDataFromPdfObject - Same as GetFormData(), except that it operates on XML data.
	
	    PROTOTYPE
	        $object		=  $pdf -> GetFormDataFromPdfObject ( $pdf_data ) ;
	
	    DESCRIPTION
	        Behaves the same as GetFormData(), except that it takes as input the XML contents of a PDF object.
	
	    PARAMETERS
	        $pdf_data (string) -
	                XML data coming from the PDF file.
	
	    RETURN VALUE
	        Returns an object of the class, as defined by the template specified to PdfToTextFormDefinitions
		class constructor.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetFormDataFromPdfObject ( $pdf_data )
	   {
		// simplexml_ functions do not like tags that contain a colon - replace them with a dash
		$pdf_data	=  preg_replace ( '/(<[^:]+?)(:)/', '$1-', $pdf_data ) ;

		// Load the xml data 
		$xml		=  simplexml_load_string ( $pdf_data ) ;

		// Get the form field values
		$fields		=  array ( ) ;

		$this -> __get_pdfform_data ( $fields, $xml ) ;

		// Return the object
		return ( $this -> GetFormData ( $fields ) ) ;
	    }


	// __getpdfform_data -
	//	Retrieve the form field values from the specified PDF object, specified as XML
	private function  __get_pdfform_data ( &$fields, $xml )
	   {
		$tag_name		=  $xml -> getName ( ) ;

		if  ( isset ( $this -> PdfDefinitions [ $tag_name ] ) )
			$fields [ $tag_name ]	=  ( string )  $xml ;
		else
		   {
			foreach  ( $xml -> children ( )  as  $child )
			   {
				$this -> __get_pdfform_data ( $fields, $child ) ;
			    }
		    }
	    }


	// __paranoid_checks -
	//	Checks for several kinds of inconsistencies in the supplied XML template.
	private function  __paranoid_checks ( )
	   {
		// Check that field names, PDF field names and constant names are unique
		$names			=  array ( ) ;
		$pdf_names		=  array ( ) ;
		$constant_names		=  array ( ) ;

		foreach  ( $this -> FieldDefinitions  as  $def )
		   {
			if  ( ! isset ( $this -> PdfDefinitions [ $def -> PdfName ] ) )
				error ( new PdfToTextFormException ( "Field \"{$def -> PdfName}\" is not defined in the PDF file." ) ) ;

			if  ( isset ( $names [ $def -> Name ] ) )
				error ( new PdfToTextFormException ( "Field \"{$def -> Name}\" is defined more than once." ) ) ;

			$names [ $def -> Name ]		=  true ;

			if  ( isset ( $pdf_names [ $def -> PdfName ] ) )
				error ( new PdfToTextFormException ( "PDF Field \"{$def -> PdfName}\" is referenced more than once." ) ) ;

			$pdf_names [ $def -> PdfName ]	=  true ;

			foreach  ( $def -> Constants  as  $constant )
			   {
				$constant_name		=  $constant [ 'name' ] ;

				if  ( isset ( $constant_names [ $constant_name ] )  &&  $constant_names [ $constant_name ]  !=  $constant [ 'value' ] )
					error ( new PdfToTextFormException ( "Constant \"$constant_name\" is defined more than once with different values." ) ) ;

				$constant_names [ $constant_name ]	=  $constant [ 'value' ] ;
			    }
		    }

		// Check that group names are unique and that the fields they are referencing exist
		$group_names	=  array ( ) ;

		foreach ( $this -> Groups  as  $group )
		   {
			if  ( isset ( $group_names [ $group [ 'name' ] ] ) )
				error ( new PdfToTextFormException ( "Group \"{$group [ 'name' ]}\" is defined more than once." ) ) ;

			if  ( isset ( $names [ $group [ 'name' ] ] ) )
				error ( new PdfToTextFormException ( "Group \"{$group [ 'name' ]}\" has the same name as an existing field." ) ) ;

			foreach ( $group [ 'fields' ]  as  $field_name )
			   {
				if  ( ! isset ( $names [ $field_name ] ) )
					error ( new PdfToTextFormException ( "Field \"$field_name\" of group \"{$group [ 'name' ]}\" does not exist." ) ) ;
			    }
		    }
	    }
    }


/*==============================================================================================================

    class PdfToTextFormFieldDefinition -
        Contains an XML template form field definition.

  ==============================================================================================================*/
class  PdfToTextFormFieldDefinition	// extends  Object
   {
	// Supported field types
	const		TYPE_STRING		=  1 ;			// String
	const		TYPE_CHOICE		=  2 ;			// Choice (must have <constant> subtags)

	// Official name (as it will appear in the class based on the XML template)
	public		$Name			=  false ;
	// Field name, as specified in the input PDF file
	public		$PdfName		=  false ;
	// Field type
	public		$Type			=  self::TYPE_STRING ;
	// Available constant values for this field when the "type" attribute has the value "choice"
	public		$Constants		=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Builds the field definition object.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $field_node ) 
	   {
		// Loop through attributes
		foreach  ( $field_node -> attributes ( )  as  $attribute_name => $attribute_value )
		   {
			switch  ( strtolower ( $attribute_name ) )
			   {
				// "name" attribute :
				//	Specifies the field name as it will appear in the output class. Must be a valid PHP name.
				case	'name' :
					$this -> Name		=  PdfToTextFormDefinition::ValidatePhpName ( ( string ) $attribute_value ) ;
					break ;

				// "form-field" attribute :
				//	Corresponding field name in the input PDF form.
				case	'form-field' :
					$this -> PdfName	=  ( string ) $attribute_value ;
					break ;

				// "type" :
				//	Field type. Can be either :
				//	- "string" :
				//		The field value can be any type of string.
				//	- "choice" :
				//		The field value has one of the values defined by the <case> or <default> subtags.
				case	'type' :
					switch ( strtolower ( ( string ) $attribute_value ) )
					   {
						case	'string' :
							$this -> Type	=  self::TYPE_STRING ;
							break ;
							
						case	'choice' :
							$this -> Type	=  self::TYPE_CHOICE ;
							break ;

						default :
							error ( new PdfToTextFormException ( "Invalid value \"$attribute_value\" for the \"$attribute_name\" attribute of the <field> tag." ) ) ;
					    }
			    }
		    }

		// The "name" and "form-field" attributes are mandatory
		if  ( ! $this -> Name )
			error ( new PdfToTextFormException ( "The \"name\" attribute is mandatory for the <field> tag." ) ) ;

		if  ( ! $this -> PdfName )
			error ( new PdfToTextFormException ( "The \"form-field\" attribute is mandatory for the <field> tag." ) ) ;

		// For "type=choice" entries, we have to look for <case> or <default> subtags
		if  ( $this -> Type  ===  self::TYPE_CHOICE )
		   {
			foreach  ( $field_node -> children ( )  as  $child )
			   {
				$tag_name	=  $child -> getName ( ) ;
				$lcname		=  strtolower ( $tag_name ) ;
				$is_default	=  false ;

				switch ( $lcname )
				   {
					// Default value to be used when no PDF field value matches the defined constants
					case	'default' :
						$is_default		=  true ;

					// "case" attribute :
					//	Maps a value to  constant name that will be defined in the generated class.
					case	'case' :
						$constant_value		=  "" ;
						$constant_name		=  false ;

						// Retrieve attributes
						foreach  ( $child -> attributes ( )  as  $attribute_name => $attribute_value )
						   {
							switch  ( strtolower ( $attribute_name ) )
							   {
								// "value" attribute :
								//	PDF form field value.
								case	'value'	:
									$constant_value		=  ( string ) $attribute_value ;
									break ;

								// "constant" attribute :
								//	Associated constant.
								case	'constant' :
									$constant_name		=  PdfToTextFormDefinition::ValidatePhpName ( ( string ) $attribute_value ) ;
									break ;

								// Bail out if any unrecognized attribute has been specified
								default :
									error ( new PdfToTextFormException ( "Invalid tag <$tag_name> in <field> definition." ) ) ;
							    }
						    }

						// Each <case> entry must have a "constant" attribute
						if  ( $constant_value  ===  false  &&  ! $is_default )
							error ( new PdfToTextFormException ( "Missing constant value in <case> tag." ) ) ;

						if  ( $constant_name  ===  false )
							error ( new PdfToTextFormException ( "Attribute \"constant-name\" is required for <$tag_name> tag." ) ) ;

						// Add this to the list of existing constants
						$this -> Constants []	=  array
						   (
							'name'		=>  $constant_name,
							'value'		=>  $constant_value,
							'default'	=>  $is_default
						    ) ;

						break ;

					// Check for unrecognized tags
					default :
						error ( new PdfToTextFormException ( "Invalid tag <$tag_name> in <field> definition." ) ) ;
				    }
			    }
		    }
	    }
    }


/*==============================================================================================================

    class PdfToTextFormData -
        Base class for all Pdf form templates data.

  ==============================================================================================================*/
class  PdfToTextFormData		// extends  Object 
   {
	// Doc comments provide information about form data fields (mainly to handle grouped field values)
	// The $__Properties array gives information about the form data fields themselves
	private		$__Properties	=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Retrieve information about the derived class properties, which are specified by the derived class
		generated on the fly.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( )
	   {
		// Get class properties
		$reflection	=  new ReflectionClass ( $this ) ;
		$properties	=  $reflection -> getProperties ( ) ;
		
		// Loop through class properties
		foreach  ( $properties  as  $property )
		   {
			$propname	=  $property -> getName ( ) ;
			$doc_comment	=  $property -> getDocComment ( ) ;

			$fields		=  false ;
			$separator	=  false ;

			// A doc comment may indicate either :
			// - A form data field (@formdata)
			// - A grouped field ; in this case, we will have the following tags :
			//	. @formdata
			//	. @group(field_list) : list of fields grouped for this property
			//	. @separator(string) : a separator used when catenating grouped fields
			if  ( $doc_comment ) 
			   {
				// The @formdata tag must be present
				if  ( strpos ( $doc_comment, '@formdata' )  ===  false )
					continue ;

				// @group(fields) pattern
				if  ( preg_match ( '/group \s* \( \s* (?P<fields> [^)]+) \)/imsx', $doc_comment, $match ) )
				   {
					$items	=  explode ( ',', $match [ 'fields' ] ) ;
					$fields =  array ( ) ;

					foreach  ( $items  as  $item )
						$fields	[]	=  $item ;
				    }

				// @separator(string) pattern
				if  ( preg_match ( '/separator \s* \( \s* (?P<separator> ( (\\\)) | (.) )+  \) /imsx', $doc_comment, $match ) )
				   {
					$separator	=  stripslashes ( $match [ 'separator' ]) ;
				    }
			     }
			// Ignore non-formdata properties
			else 
				continue ;

			// Property belongs to the form - add it to the list of available properties
			$this -> __Properties [ $propname ]	=  array
			   (
				'name'		=>  $propname,
				'fields'	=>  $fields,
				'separator'	=>  $separator
			    ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    __get -
		Returns the underlying property value for this PDF data field.
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __get ( $member )
	   {
		if  ( ! isset ( $this -> __Properties [ $member ] ) )
			warning ( new PdfToTextFormException ( "Undefined property \"$member\"." ) ) ;

		return ( $this -> $member ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    __set -
		Sets the underlying property value for this PDF data field.
		When the property is a compound one, sets individual members as well.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __set  ( $member, $value )
	   {
		// Property exists : some special processing will be needed
		if  ( isset ( $this -> __Properties [ $member ] ) )
		   {
			$prop_entry	=  $this -> __Properties [ $member ] ;

			// Non-compound property
			if  ( ! $prop_entry [ 'fields' ] )
			   {
				$this -> $member	=  $value ;

				// However, we have to check that this property belongs to a compound property and change
				// the compound property valu accordingly
				foreach  ( $this -> __Properties  as  $name => $property )
				   {
					if  ( $property [ 'fields' ] )
					   {
						if  ( in_array ( $member, $property [ 'fields' ] ) )
						   {
							$values		=  array ( ) ;

							foreach  ( $property [ 'fields' ]  as  $value )
								$values	[]	=  $this -> $value ;

							// Change compound property value accordingly, using the specified separator
							$this -> $name	=  implode ( $property [ 'separator' ], $values ) ;
						    }
					    }
				    }
			    }
			// Compound property : we will have to explode it in separate parts, using the compound property separator,
			// then set individual property values
			else
			   {
				$values		=  explode ( $prop_entry [ 'separator' ], $value ) ;
				$value_count	=  count ( $values ) ;
				$field_count	=  count ( $prop_entry [ 'fields' ] ) ;

				if  ( $value_count  <  $field_count )
					error ( new PdfToTextFormException ( "Not enough value parts specified for the \"$member\" property ($value)." ) ) ;
				else if  ( $value_count  >  $field_count )
					error ( new PdfToTextFormException ( "Too much value parts specified for the \"$member\" property ($value)." ) ) ;

				$this -> $member	=  $value ;

				for  ( $i = 0 ; $i  <  $value_count ; $i ++ )
				   {
					$sub_member		=  $prop_entry [ 'fields' ] [$i] ;
					$this -> $sub_member	=  $values [$i] ;
				    }
			    }
		    }
		// Property does not exist : let PHP act as the default way
		else
			$this -> $member	=  $value ;
	    }
    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                  CAPTURE DEFINITION MANAGEMENT                                   ******
 ******         (none of the classes listed here are meant to be instantiated outside this file)         ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    class PdfToTextCaptureDefinitions -
        Holds text capture definitions, whose XML data has been supplied to the PdfToText::SetCapture() method.

  ==============================================================================================================*/
class  PdfToTextCaptureDefinitions	// extends		Object 
					implements	ArrayAccess, Countable, Iterator
   {
	// Shape definitions - The actual objects populating this array depend on the definitions supplied
	// (rectangle, etc.)
	protected	$ShapeDefinitions		=  array ( ) ;

	// Shape field names - used for iteration
	private		$ShapeNames ;

	// Page count 
	private		$PageCount			=  false ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR -
		Analyzes the XML data defining the areas to be captured.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $xml_data )
	   {
		$xml		=  simplexml_load_string ( $xml_data ) ;
		$root_entry	=  $xml -> getName ( ) ;

		// Root tag must be <captures>
		if  ( strcasecmp ( $root_entry, "captures" ) ) 
			error ( new PdfToTextCaptureException ( "Root entry must be <captures>, <$root_entry> was found." ) ) ;

		// Process the child nodes
		foreach  ( $xml -> children ( )   as  $child )
		   {
			$tag_name		=  $child -> getName ( ) ;

			switch  ( strtolower ( $tag_name ) )
			   {
				// <rectangle> :
				//	An rectangle whose dimensions are given in the <page> subtags.
				case	'rectangle' :
					$shape_object	=  new PdfToTextCaptureRectangleDefinition ( $child ) ;
					break ;

				// <columns> :
				//	A definition of columns and their applicable pages.
				case	'lines' :
					$shape_object	=  new PdfToTextCaptureLinesDefinition ( $child ) ;
					break ;

				// Complain if an unknown tag is found
				default :
					error ( new PdfToTextCaptureException ( "Invalid tag <$tag_name> found in root tag <captures>." ) ) ;
			    }

			// Shape names must be unique within the definitinos
			if  ( isset ( $this -> ShapeDefinitions [ $shape_object -> Name ] ) )
				error ( new PdfToTextCaptureLinesDefinition ( "The shape named \"{$shape_object -> Name}\" has been defined more than once." ) ) ;
			else
				$this -> ShapeDefinitions [ $shape_object -> Name ]	=  $shape_object ;
		    }

		// Build an array of shape names for the iterator interface
		$this -> ShapeNames	=  array_keys ( $this -> ShapeDefinitions ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetCapturedObject - Creates an object reflecting the captured data.
	
	    PROTOTYPE
	        $captures	=  $capture_definitions -> GetCapturedObject ( $document_fragments ) ;
	
	    DESCRIPTION
	        Returns an object of type PdfToTextCapturedData,containing the data that has been captured, based on
		the capture definitions.
	
	    PARAMETERS
	        $document_fragments (type) -
	                Document text fragments collected during the text layout rendering process.
	
	    RETURN VALUE
	        An object of type PdfToTextCaptures, cntaining the captured data.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetCapturedObject ( $document_fragments )
	   {
		$captures	=  array ( ) ;

		foreach  ( $this -> ShapeDefinitions  as  $shape )
		   {
			$capture	=  $shape -> ExtractAreas ( $document_fragments ) ;

			foreach  ( $capture  as  $page => $items )
			   {
				$captures [ $page ] []	=  $items ;
			    }
		    }

		 $captured_object	=  new PdfToTextCaptures ( $captures ) ;

		 return ( $captured_object ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        SetPageCount - Defines the total number of pages in the document.
	
	    PROTOTYPE
	        $shape -> SetPageCount ( $count ) ;
	
	    DESCRIPTION
	        At the time when XML definitions are processed, the total number of pages in the document is not yet 
		known. Moreover, page ranges or page numbers can be expressed relative to the last page of the 
		document (for example : 1..$-1, which means "from the first page to the last page - 1).
		Setting the page count once it is known allows to process the expressions specified in the "number"
		attribute of the <pages> tag so that the expressions are transformed into actual page numbers.
	
	    PARAMETERS
	        $count (integer) -
	                Number of pages in the document.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetPageCount ( $count )
	   {
		$this -> PageCount	=  $count ;

		foreach  ( $this -> ShapeDefinitions  as  $def )
		   {
			$def ->  SetPageCount ( $count ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetNodeAttributes - Retrieves an XML node's attributes.
	
	    PROTOTYPE
	        $result		=  PdfToTextCaptureDefinitions::GetNodeAttributes ( $node, $attributes ) ;
	
	    DESCRIPTION
	        Retrieves the attributes defined for the specified XML node.
	
	    PARAMETERS
	        $node (SimpleXMLElement) -
	                Node whose attributes are to be extracted.

		$attributes (associative array) -
			Associative array whose keys are the attribute names and whose values define a boolean 
			indicating whether the attribute is mandatory or not.
	
	    RETURN VALUE
	        Returns an associative whose key are the attribute names and whose values are the attribute values,
		specified as a string.
		For optional unspecified attributes, the value will be boolean false.
	
	    NOTES
	        The method throws an exception if the node contains an unknown attribute, or if a mandatory attribute
		is missing.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  GetNodeAttributes  ( $node, $attributes )
	   {
		$tag_name	=  $node -> getName ( ) ;

		// Build the initial value for the resulting array
		$result		=  array ( ) ;

		foreach  ( array_keys ( $attributes )  as  $name )
			$result	[ $name ]	=  false ;

		// Loop through node attributes
		foreach  ( $node -> attributes ( )  as  $attribute_name => $attribute_value )
		   {
			$attribute_name		=  strtolower ( $attribute_name ) ;

			// Check that the attributes exists ; if yes, add it to the resulting array
			if  ( isset ( $attributes [ $attribute_name ] ) )
				$result [ $attribute_name ]	=  ( string ) $attribute_value ;
			// Otherwise, throw an exception
			else
				error ( new PdfToTextCaptureLinesDefinition ( "Undefined attribute \"$attribute_name\" for node <$tag_name>." ) ) ;
		    }

		// Check that all mandatory attributes have been specified
		foreach  ( $attributes  as  $attribute_name => $mandatory )
		   {
			if  ( $mandatory  &&  $result [ $attribute_name ]  ===  false )
				error ( new PdfToTextCaptureLinesDefinition ( "Undefined attribute \"$attribute_name\" for node <$tag_name>." ) ) ;
		    }

		// All done, return
		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        GetBooleanAttribute - Returns a boolean value associated to a string.
	
	    PROTOTYPE
	        $bool	=  PdfToTextCaptureDefinitions::GetBooleanValue ( $value ) ;
	
	    DESCRIPTION
	        Returns a boolean value corresponding to a boolean specified as a string.
	
	    PARAMETERS
	        $value (string) -
	                A boolean value represented as a string. 
			The strings 'true', 'yes', 'on' and '1' will be interpreted as boolean true.
			The strings 'false', 'no', 'off' and '0' will be interpreted as boolean false.
	
	    RETURN VALUE
	        The boolean value corresponding to the specified string.
	
	    NOTES
	        An exception is thrown if the supplied string is incorrect.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public static function  GetBooleanAttribute ( $value )
	   {
		$lcvalue	=  strtolower ( $value ) ;

		if  ( $lcvalue  ===  'true'  ||  $lcvalue  ===  'on'  ||  $lcvalue  === 'yes'  ||  $lcvalue  ===  '1'  ||  $value  ===  true )
			return ( true ) ;
		else if  ( $lcvalue  ===  'false'  ||  $lcvalue  ===  'off'  ||  $lcvalue  === 'no'  ||  $lcvalue  ===  '0'  ||  $value  ===  false )
			return(  false ) ;
		else
			error ( new PdfToTextCaptureLinesDefinition ( "Invalid boolean value \"$value\"." ) ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Interfaces implementations.

	 *-------------------------------------------------------------------------------------------------------------*/

	// Countable interface
	public function  count ( )
	   { return ( count ( $this -> ShapeDefinitions ) ) ; }


	// ArrayAccess interface
	public function  offsetExists ( $offset )
	   { return ( isset ( $this -> ShapeDefinitions [ $offset ] ) ) ; }


	public function  offsetGet ( $offset )
	   { return ( $this -> ShapeDefinitions [ $offset ] ) ; }


	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextException ( "Unsupported operation" ) ) ; }


	public function  offsetunset ( $offset )
	   { error ( new PdfToTextException ( "Unsupported operation" ) ) ; }


	// Iterator interface -
	//	Iteration is made through shape names, which are supplied by the $ShapeNames property
	private		$__iterator_index	=  0 ;

	public function  rewind ( )
	   { $this -> __iterator_index = 0 ; }

	public function  valid ( )
	   { return ( $this -> __iterator_index  >=  0  &&  $this -> __iterator_index  <  count ( $this -> ShapeNames ) ) ; }

	public function  key ( )
	   { return ( $this -> ShapeNames [ $this -> __iterator_index ] ) ; }

	public function  next ( )
	   { $this -> __iterator_index ++ ; }

	public function  current ( )
	   { return ( $this -> ShapeDefinitions [ $this -> ShapeNames [ $this -> __iterator_index ] ] ) ; }
    }


/*==============================================================================================================

    class PdfToTextCaptureShapeDefinition -
        Base class for capturing shapes.

  ==============================================================================================================*/
abstract class  PdfToTextCaptureShapeDefinition		//extends  Object
   {
	const	SHAPE_RECTANGLE		=  1 ;
	const	SHAPE_COLUMN		=  2 ;
	const	SHAPE_LINE		=  3 ;

	// Capture name
	public		$Name ;
	// Capture type - one of the SHAPE_* constants, assigned by derived classes.
	public		$Type ;
	// Applicable pages for this capture
	public		$ApplicablePages ;
	// Areas per page for this shape
	public		$Areas		=  array ( ) ;
	// Separator used when multiple elements are covered by the same shape
	public		$Separator	=  " " ;


	/*--------------------------------------------------------------------------------------------------------------
	
	     Constructor -
		Initializes the base capture class.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $type )
	   {
		$this -> Type			=  $type ;
		$this -> ApplicablePages	=  new PdfToTextCaptureApplicablePages ( ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     SetPageCount -
		Sets the page count, so that all the applicable pages can be determined.
		Derived classes can implement this function if some additional work is needed.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetPageCount ( $count ) 
	   {
		$this -> ApplicablePages -> SetPageCount ( $count ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     GetFragmentData -
		Extracts data from a text fragment (text + coordinates).

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetFragmentData ( $fragment, &$text, &$left, &$top, &$right, &$bottom )
	   {
		$left		=  ( double ) $fragment [ 'x' ] ;
		$top		=  ( double ) $fragment [ 'y' ] ;
		$right		=  $left + ( double ) $fragment [ 'width' ] - 1 ;
		$bottom		=  $top  - ( double ) $fragment [ 'font-height' ] ;
		$text		=  $fragment [ 'text' ] ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     GetAttributes -
		Retrieves the attributes of the given XML node. Processes the following attributes, which are common to
		all shapes :
		- Name
		- Separator

	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetAttributes ( $node, $attributes  =  array ( ) )
	   {
		$attributes		=  array_merge ( $attributes, array ( 'name' => true, 'separator' => false ) ) ;
		$shape_attributes	=  PdfToTextCaptureDefinitions::GetNodeAttributes ( $node, $attributes ) ;
		$this -> Name		=  $shape_attributes [ 'name' ] ;

		if  ( $shape_attributes [ 'separator' ]  !==  false )
			$this -> Separator	=  PdfToText::Unescape ( $shape_attributes [ 'separator' ] ) ;

		return ( $shape_attributes ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     ExtractAreas -
		Extracts text contents from the document fragments.

	 *-------------------------------------------------------------------------------------------------------------*/
	public abstract function  ExtractAreas ( $document_fragments ) ;
    }


/*==============================================================================================================

    class PdfToTextCaptureRectangleDefinition -
        A shape for capturing text in rectangle areas.

  ==============================================================================================================*/
class	PdfToTextCaptureRectangleDefinition		extends  PdfToTextCaptureShapeDefinition
   {
	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR -
		Analyzes the contents of a <rectangle> XML node, which contains <page> child node giving the
		applicable pages and the rectangle dimensions.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct  ( $node )
	   {
		parent::__construct ( self::SHAPE_RECTANGLE ) ;

		$this -> GetAttributes ( $node ) ;

		// Loop through node's children
		foreach  ( $node -> children ( )  as  $child )
		   {
			$tag_name	=  $child -> getName ( ) ;

			switch  ( strtolower ( $tag_name ) )
			   {
				// <page> tag : applicable page(s)
				case	'page' :
					// Retrieve the specified attributes
					$page_attributes	=  PdfToTextCaptureDefinitions::GetNodeAttributes 
					   (
						$child,
						array 
						   (
							'number'	=>  true,
							'left'		=>  true,
							'right'		=>  false,
							'top'		=>  true,
							'bottom'	=>  false,
							'width'		=>  false,
							'height'	=>  false 
						    )
					    ) ;

					$page_number	=  $page_attributes [ 'number' ] ;

					// Add this page to the list of applicable pages for this shape
					$this -> ApplicablePages -> Add ( $page_number, $page_attributes ) ;

					break ;

				// Other tag : throw an exception
				default :
					error ( new PdfToTextCaptureException ( "Invalid tag <$tag_name> found in root tag <rectangle>." ) ) ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     ExtractAreas -
		Extracts text contents from the document fragments.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  ExtractAreas ( $document_fragments )
	   {
		$result		=  array ( ) ;

		// Loop through document fragments
		foreach  ( $document_fragments  as  $page  =>  $page_contents )
		   {
			$fragments		=  $page_contents [ 'fragments' ] ;

			// Ignore pages that are not applicable
			if  ( ! isset ( $this -> ApplicablePages -> PageMap [ $page ] ) ) 
				continue ;

			// Loop through each text fragment of the page
			foreach  ( $fragments  as  $fragment )
			   {
				$this -> GetFragmentData ( $fragment, $text, $left, $top, $right, $bottom ) ;

				// Only handle text fragments that are within the specified area
				if  ( $this -> Areas [ $page ] -> Contains ( $left, $top, $right, $bottom ) )
				   {
					// Normally, rectangle shapes are used to capture a single line...
					if  ( ! isset ( $result [ $page ] ) )
						$result [ $page ]	=  new PdfToTextCapturedRectangle ( $page, $this -> Name, $text, $left, $top, $right, $bottom, $this ) ;
					// ... but you can also use them to capture multiple lines ; in this case, the "separator" attribute of the <rectangle> tag will 
					// be used to separate items
					else
					   {
						$existing_area	=  $result [ $page ] ;

						$existing_area -> Top			=  max ( $existing_area -> Top   , $top    ) ;
						$existing_area -> Bottom		=  min ( $existing_area -> Bottom, $bottom ) ;
						$existing_area -> Left			=  min ( $existing_area -> Left  , $left   ) ;
						$existing_area -> Right			=  max ( $existing_area -> Right , $right  ) ;
						$existing_area -> Text		       .=  $this -> Separator . $text ;
					    }
				    }
			    }
		    }


		// Provide empty values for pages which did not capture a rectangle shape
		$added_missing_pages	=  false ;

		foreach  ( $this -> ApplicablePages  as  $page => $applicable )
		   {
			if  ( ! isset ( $result [ $page ] ) )
			   {
				$result [ $page ]	=  new PdfToTextCapturedRectangle ( $page, $this -> Name, '', 0, 0, 0, 0, $this ) ;
				$added_missing_pages	=  true ;
			    }
		    }

		if  ( $added_missing_pages )	// Sort by page number if empty values were added
			ksort ( $result ) ;

		// All done, return
		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     SetPageCount -
		Ensures that an Area is created for each related page.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetPageCount ( $count ) 
	   {
		parent::SetPageCount ( $count ) ;

		// Create a rectangle area for each page concerned - this can only be done when the number of pages is known
		// (and the ApplicablePages object updated accordingly)
		foreach  ( $this -> ApplicablePages -> ExtraPageMapData  as  $page => $data )
			$this -> Areas [ $page ]		=  new PdfToTextCaptureArea ( $data ) ;
	    }
    }


/*==============================================================================================================

    class PdfToTextCaptureLinesDefinition -
        A shape for capturing text in rectangle areas.

  ==============================================================================================================*/
class	PdfToTextCaptureLinesDefinition		extends  PdfToTextCaptureShapeDefinition
   {
	// Column areas
	public		$Columns		=  array ( ) ;
	// Top and bottom lines
	public		$Tops			=  array ( ) ;
	public		$Bottoms		=  array ( ) ;
	// Column names
	private		$ColumnNames		=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR -
		Analyzes the contents of a <columns> XML node, which contains <page> nodes giving a part of the column
		dimensions, and <column> nodes which specify the name of the column and the remaining coordinates,
		such as "left" or "width"
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct  ( $node )
	   {
		parent::__construct ( self::SHAPE_COLUMN ) ;

		$shape_attributes	=  $this -> GetAttributes ( $node, array ( 'default' => false ) ) ;
		$column_default		=  ( $shape_attributes [ 'default' ] )  ?  $shape_attributes [ 'default' ] : '' ;

		// Loop through node's children
		foreach  ( $node -> children ( )  as  $child )
		   {
			$tag_name	=  $child -> getName ( ) ;

			switch  ( strtolower ( $tag_name ) )
			   {
				// <page> tag 
				case	'page' :
					// Retrieve the specified attributes
					$page_attributes	=  PdfToTextCaptureDefinitions::GetNodeAttributes 
					   (
						$child,
						array 
						   (
							'number'	=>  true,
							'top'		=>  true,
							'height'	=>  true,
							'bottom'	=>  false
						    )
					    ) ;

					// We have to store the y-coordinate of the first and last lines, to determine until which
					// position we have to check for column contents.
					// The "top" and "bottom" attributes of the <page> tag actually determine the top and bottom
					// y-coordinates where to search for columns. However, we will have to rename the "bottom"
					// attribute to "column-bottom", in order for it not to be mistaken with actual column rectangle
					// (only the "height" attribute of the <page> tag gives the height of a line)
					$page_attributes [ 'column-top' ]	=  $page_attributes [ 'top' ] ;
					$page_attributes [ 'column-bottom' ]	=  ( double ) $page_attributes [ 'bottom' ] ;
					unset ( $page_attributes [ 'bottom' ] ) ;
					
					// Add this page to the list of applicable pages for this shape
					$this -> ApplicablePages -> Add ( $page_attributes [ 'number' ], $page_attributes ) ;

					break ;

				// <column> tag :
				case	'column' :
					$column_attributes	=  PdfToTextCaptureDefinitions::GetNodeAttributes 
					   (
						$child,
						array 
						   (
							'name'		=>  true,
							'left'		=>  false,
							'right'		=>  false,
							'width'		=>  false,
							'default'	=>  false
						    )
					    ) ;	

					$column_name		=  $column_attributes [ 'name' ] ;

					// Build the final default value, if any one is specified ; the following special constructs are processed :
					// - "%c" :
					//	Replaced by the column name.
					// - "%n" :
					//	Replaced by the column index (starting from zero).
					if  ( ! $column_attributes [ 'default' ] )
						$column_attributes [ 'default' ]	=  $column_default ;

					$substitutes		=  array
					   (
						'%c'		=>  $column_name,
						'%n'		=>  count ( $this -> Columns )
					    ) ;

					$column_attributes [ 'default' ]	=  str_replace
					   (
						array_keys ( $substitutes ),
						array_values ( $substitutes ),
						$column_attributes [ 'default' ]
					    ) ;

					// Add the column definition to this object
					if  ( ! isset ( $this -> Columns [ $column_name ] ) )
					   {
						$this -> Columns [ $column_attributes [ 'name' ] ]	=  $column_attributes ;
						$this -> ColumnNames []					=  $column_attributes [ 'name' ] ;
					    }
					else
						error ( new PdfToTextCaptureException ( "Column \"$column_name\" is defined more than once." ) ) ;

					break ;

				// Other tag : throw an exception
				default :
					error ( new PdfToTextCaptureException ( "Invalid tag <$tag_name> found in root tag <rectangle>." ) ) ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     ExtractAreas -
		Extracts text contents from the document fragments.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  ExtractAreas ( $document_fragments )
	   {
		$result		=  array ( ) ;

		// Loop through each page of document fragments
		foreach  ( $document_fragments  as  $page  =>  $page_contents )
		   {
			$fragments		=  $page_contents [ 'fragments' ] ;

			// Ignore this page if not included in the <columns> definition
			if  ( ! isset ( $this -> ApplicablePages -> PageMap [ $page ] ) ) 
				continue ;

			// <columns> definition only gives the location of the first line of each column, together
			// with its height.
			// We will build as many new column areas as can fit on one page
			$this_page_areas	=  $this -> Areas [ $page ] ;
			$column_areas		=  array ( ) ;

			for  ( $i = 0, $count = count ( $this_page_areas ) ; $i  <  $count ; $i ++ )
			   {
				// For now, duplicate the existing column areas - they will represent the 1st line of columns
				$this_page_area		=  $this_page_areas [$i] ;
				$new_area		=  clone ( $this_page_area ) ;
				$column_areas [0] []	=  $new_area ;
				$line_height		=  $new_area -> Height ;
				$current_top		=  $new_area -> Top - $line_height ;
				$current_line		=  0 ;

				// Then build new column areas for each successive lines
				while  ( $current_top - $line_height  >=  0 )
				   {
					$current_line ++ ;
					$new_area				 =  clone ( $new_area ) ;
					$new_area -> Top			-=  $line_height ;
					$new_area -> Bottom			-=  $line_height ;

					$column_areas [ $current_line ]	[]	 =  $new_area ;
					$current_top				-=  $line_height ;
				    }
			    }

			// Now extract the columns, line per line, from the current page's text fragments
			$found_lines		=  array ( ) ;

			foreach  ( $fragments  as  $fragment )
			   {
				$this -> GetFragmentData ( $fragment, $text, $left, $top, $right, $bottom ) ;

				// Loop through each line of column areas, built from the above step
				foreach ( $column_areas  as  $line => $column_areas_per_name )
				    {
					$index	=  0 ;			// Column index

					// Process each column area
					foreach  ( $column_areas_per_name  as  $column_area )
					   {
						// ... but only do something if the current column area is contained in the current fragment
						if  ( $column_area -> Contains ( $left, $top, $right, $bottom ) )
						   {
							// The normal usage will be to capture one-line columns...
							if  ( ! isset ( $found_lines [ $line ] [ $column_area -> Name ] ) )
							   {
								$found_lines [ $line ] [ $column_area -> Name ]	=
									new PdfToTextCapturedColumn ( $page, $column_area -> Name, $text, 
										$left, $top, $right, $bottom, $this ) ;
							    }
							// ... but you can also use them to capture multiple lines ; in this case, the "separator" attribute of the <lines> or
							// <column> tag will be used to separate items
							else
							   {
								$existing_area	=  $found_lines [ $line ] [ $column_area -> Name ] ;

								$existing_area -> Top			=  max ( $existing_area -> Top   , $column_area -> Top    ) ;
								$existing_area -> Bottom		=  min ( $existing_area -> Bottom, $column_area -> Bottom ) ;
								$existing_area -> Left			=  min ( $existing_area -> Left  , $column_area -> Left   ) ;
								$existing_area -> Right			=  max ( $existing_area -> Right , $column_area -> Right  ) ;
								$existing_area -> Text		       .=  $this -> Separator . $text ;
							    }
						    }	

						$index ++ ;
					    }
				     }
			    }

			// A final pass to provide default values for empty columns (usually, column values that are not represented in the PDF file)
			// Also get the surrounding box for the whole line
			$final_lines		=  array ( ) ;

			foreach  ( $found_lines  as  $line => $columns_line )
			   {
				foreach  ( $this -> ColumnNames  as  $column_name )
				   {
					if  ( ! isset ( $columns_line [ $column_name ] ) ) 
					   {
						$columns_line [ $column_name ]	=
							new PdfToTextCapturedColumn ( $page, $column_name, $this -> Columns [ $column_name ] [ 'default' ], 0, 0, 0, 0, $this ) ; 
					    }
				    }

				// Get the (left,top) coordinates of the line
				$line_left	=  $found_lines [ $line ] [ $this -> ColumnNames [0] ] -> Left ;
				$line_top	=  $found_lines [ $line ] [ $this -> ColumnNames [0] ] -> Top ;

				// Get the (right,bottom) coordinates - we have to find the last column whose value is not a default value
				// (and therefore, has a non-zero Right coordinate)
				$last		=  count ( $this -> ColumnNames ) - 1 ;
				$line_right	=  0 ;
				$line_bottom	=  0 ;

				while  ( $last  >=  0  &&  ! $columns_line [ $this -> ColumnNames [ $last ] ] -> Right )
					$last -- ;

				if  ( $last  >  0 )
				   {
					$line_right	=  $columns_line [ $this -> ColumnNames [ $last ] ] -> Right ;
					$line_bottom	=  $columns_line [ $this -> ColumnNames [ $last ] ] -> Bottom ;
				    }

				// Create a CaptureLine entry 
				$final_lines []	=  new PdfToTextCapturedLine ( $page, $this -> Name, $columns_line, $line_left, $line_top, $line_right, $line_bottom, $this ) ;
			    }

			// The result for this page will be a CapturedLines object
			$result [ $page ]	=  new  PdfToTextCapturedLines ( $this -> Name, $page, $final_lines ) ;
		    }

		// All done, return
		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	     SetPageCount -
		Extracts text contents from the document fragments.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetPageCount ( $count )
	   {
		parent::SetPageCount ( $count ) ;

		foreach  ( $this -> ApplicablePages  as $page => $applicable )
		   {
			if  ( ! $applicable )
				continue ;

			foreach  ( $this -> Columns  as  $column )
			   {
				if  ( ! isset ( $this -> Tops [ $page ] ) )
				   {
					$this -> Tops    [ $page ]		=  ( double ) $this -> ApplicablePages -> ExtraPageMapData [ $page ] [ 'column-top' ] ;
					$this -> Bottoms [ $page ]		=  ( double ) $this -> ApplicablePages -> ExtraPageMapData [ $page ] [ 'column-bottom' ] ;
				    }

				$area	=  new PdfToTextCaptureArea ( $column, $this -> ApplicablePages -> ExtraPageMapData [ $page ], $column [ 'name' ] ) ;

				$this -> Areas [ $page ] []	=  $area ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Support functions.

	 *-------------------------------------------------------------------------------------------------------------*/
    }


/*==============================================================================================================

    class PdfToTextCaptureApplicablePages -
        Holds a list of applicable pages given by the "number" attribute of <page> tags.

  ==============================================================================================================*/
class  PdfToTextCaptureApplicablePages		//extends		Object
						implements	ArrayAccess, Countable, Iterator
   {
	// Ranges of pages, as given by the "number" attribute of the <page> tag. Since a page number expression
	// can refer to the last page ("$"), and the total number of pages in the document is not yet known at the
	// time of object instantiation, we have to store all the page ranges as is.
	protected	$PageRanges		=  array ( ) ;

	// Once the SetPageCount() method has been called (ie, once the total number of pages in the document is
	// known), then a PageMap is built ; each key is the page number, indicating whether the page applies or not.
	public		$PageMap		=  array ( ) ;

	// Extra data associated, this time, with each page in PageMap
	public		$ExtraPageMapData	=  array ( ) ;

	// Page count - set by the SetPageCount() method
	public		$PageCount		=  false ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    CONSTRUCTOR
	        Initializes the object.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( )
	   {
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Add - Add a page number(s) definition.
	
	    PROTOTYPE
	        $applicable_pages -> Add ( $page_number ) ;
	
	    DESCRIPTION
	        Add the page number(s) specified by the "number" attribute of the <pages> tag to the list of applicable
		pages.
	
	    PARAMETERS
	        $page_number (string) -
	                A string defining which pages are applicable. This can be a single page number :

				<page number="1" .../>

			or a comma-separated list of pages :

				<page number="1, 2, 10" .../>

			or range(s) of pages :

				<page number="1..10, 12..20" .../>

			The special "$" character means "last page" ; thus the following example :

				<page number="1, $-9..$" .../>

			means : "applicable pages are 1, plus the last ten pages f the document".
		
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  Add ( $page_number, $extra_data = false )
	   {
		$this -> __parse_page_numbers ( $page_number, $extra_data ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        SetPageCount - Sets the total number of pages in the document.
	
	    PROTOTYPE
	        $applicable_pages -> SetPageCount ( $count ) ;
	
	    DESCRIPTION
	        Sets the total number of pages in the document and builds a map of which pages are applicable or not.
	
	    PARAMETERS
	        $count (integer) -
	                Total number of pages in the document.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  SetPageCount ( $count )
	   {
		$this -> PageCount		=  $count ;
		$this -> PageMap		=  array ( ) ;

		// Loop through the page ranges - every single value in the ranges has been converted to an integer ;
		// the other ones, built as expressions (using "$" for example) are processed here to give the actual
		// page number
		foreach  ( $this -> PageRanges  as  $range )
		   {
			$low		=  $range [0] ;
			$high		=  $range [1] ;

			// Translate expression to an actual value for the low and high parts of the range, if not already integers
			if  ( ! is_integer ( $low ) )
				$low	=  $this -> __check_expression ( $low, $count ) ;

			if  ( ! is_integer ( $high ) )
				$high	=  $this -> __check_expression ( $high, $count ) ;

			// Expressions using "$" may lead to negative values - adjust them
			if  ( $low  <  1 )
			   {
				if  ( $high  <  1 )
					$high	=  1 ;

				$low	=  1 ;
			    }

			// Check that the range is consistent
			if  ( $low  >  $high )
				error ( new PdfToTextCaptureException ( "Low value ($low) must be less or equal to high value ($high) " .
						"in page range specification \"{$range [0]}..{$range [1]}\"." ) ) ;

			// Ignore ranges where the 'low' value is higher than the number of pages in the document
			if  ( $low  >  $count )
			    {
				warning ( new PdfToTextCaptureException ( "Low value ($low) is greater than page count ($count) " .
						"in page range specification \"{$range [0]}..{$range [1]}\"." ) ) ;
				continue ;
			     }

			// Normalize the 'high' value, so that it's not bigger than the number of pages in the document
			if  ( $high  >  $count )
				$high	=  $count ;
				
			// Complement the page map using this range
			for  ( $i = $low ; $i  <=  $high ; $i ++ )
			   {
				$this -> PageMap [$i]		=  true ;
				$this -> ExtraPageMapData [$i]	=  $range [2] ;
			    }
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Interfaces implementations.

	 *-------------------------------------------------------------------------------------------------------------*/

	// Countable interface
	public function  count ( )
	   { return ( count ( $this -> PageMap ) ) ; }


	// Array access interface
	public function  offsetExists ( $offset )
	   { return ( isset ( $this -> PageMap [ $offset ] ) ) ; }


	public function  offsetGet ( $offset )
	   { return ( ( isset ( $this -> PageMap [ $offset ] ) ) ?  true : false ) ; }


	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextException ( "Unsupported operation" ) ) ; }


	public function  offsetunset ( $offset )
	   { error ( new PdfToTextException ( "Unsupported operation" ) ) ; }


	// Iterator interface
	private		$__iterator_value	=  1 ;

	public function   rewind ( ) 
	   { $this -> __iterator_value = 1 ; }

	
	public function  valid ( )
	   { return ( $this -> __iterator_value  >=  1  &&  $this -> __iterator_value  <=  $this -> PageCount ) ; }

	
	public function  key ( )
	   { return ( $this -> __iterator_value ) ; }


	public function  next ( )
	   { $this -> __iterator_value ++ ; }


	public function  current ( )
	   { return ( ( isset ( $this -> PageMap [ $this -> __iterator_value ] ) ) ?  true : false ) ; }


	/*--------------------------------------------------------------------------------------------------------------
	
		Helper functions.

	 *-------------------------------------------------------------------------------------------------------------*/

	// __parse_page_numbers -
	//	Performs a first pass on the value of the "number" attribute of the <page> tag. Transforms range expressions
	//	when possible to integers ; keep the expression string intact when either the low or high value of a range
	//	is itself an expression, probably using the "$" (page count) character.
	private function  __parse_page_numbers ( $text, $extra_data )
	   {
		$ranges		=  explode ( ',', $text ) ;

		// Loop through comma-separated ranges
		foreach  ( $ranges  as  $range )
		   {
			$items		=  explode ( '..', $range ) ;

			// Check if current item is a range
			switch  ( count ( $items ) )
			   {
				// If not a range (ie, a single value) then make a range using that value
				// (low and high range values will be the same)
				case	1 :
					if  ( is_numeric ( $items [0] ) )
						$low	=  $high	=  ( integer ) $items [0] ;
					else
						$low	=  $high	=  trim ( $items [0] ) ;
					
					break ;

				// If range, store the low and high values
				case	2 :
					$low	=  ( is_numeric ( $items [0] ) ) ?  ( integer ) $items [0] : trim ( $items [0] ) ;
					$high	=  ( is_numeric ( $items [1] ) ) ?  ( integer ) $items [1] : trim ( $items [1] ) ;
					break ;

				// Other cases : throw an exception
				default :
					error ( new PdfToTextCaptureException ( "Invalid page range specification \"$range\"." ) ) ;
			    }

			// If the low or high range value is an expression, check at this stage that it is correct
			if  ( is_string ( $low )  &&  $this -> __check_expression ( $low )  ===  false )
				error ( new PdfToTextCaptureException ( "Invalid expression \"$low\" in page range specification \"$range\"." ) ) ;

			if  ( is_string ( $high )  &&  $this -> __check_expression ( $high )  ===  false )
				error ( new PdfToTextCaptureException ( "Invalid expression \"$high\" in page range specification \"$range\"." ) ) ;

			// Add the page range and the extra data
			$this -> PageRanges []	=  array ( $low, $high, $extra_data ) ;
		    }
	    }


	// __check_expression -
	//	Checks that a syntactically correct 
	private function  __check_expression ( $str, $count = 1 )
	    {
		$new_str	=  str_replace ( '$', $count, $str ) ;
		$value		=  @eval ( "return ( $new_str ) ;" ) ;

		return ( $value ) ;
	     }
    }


/*==============================================================================================================

    class PdfToTextCaptureArea -
        A capture area describes a rectangle, either by its top, left, right and bottom coordinates, or by 
	its top/left coordinates, and its width and height.

  ==============================================================================================================*/
class  PdfToTextCaptureArea	//extends  Object 
   {
	// List of authorzed keyword for defining the rectangle dimensions
	static private		$Keys		=  array ( 'left', 'top', 'right', 'bottom', 'width', 'height' ) ;

	// Rectangle dimensions
	private			$Left		=  false,
				$Top		=  false,
				$Right		=  false,
				$Bottom		=  false ;

	// Area name (for internal purposes)
	public			$Name ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Constructor
	
	    PROTOTYPE
	        $area	=  new PdfToTextCaptureArea ( $area, $default_area = null, $name = '' ) ;
	
	    DESCRIPTION
	        Initialize an area (a rectangle) using the supplied coordinates
	
	    PARAMETERS
	        $area (array) -
	                An associative array that may contain the following entries :

			- 'left' (double) :
				Left x-coordinate (mandatory).

			- 'top' (double) :
				Top y-coordinate (mandatory).

			- 'right (double) :
				Right x-coordinate.

			- 'bottom' (double) :
				Bottom y-coordinate.

			- 'width' (double) :
				Width of the rectangle, starting from 'left'.

			- 'height' (double) :
				Height of the rectangle, starting from 'top'.

			Either the 'right' or 'width' entries must be specified. This is the same for the 'bottom' and
			'height' entries.

		$default_area (array) -
			An array that can be used to supply default values when absent from $area.

		$name (string) -
			An optional name for this area. This information is not used by the class.
	
	    NOTES
	        Coordinate (0,0) is located at the left bottom of the page.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $area, $default_area = null, $name = '' )
	   {
		$left		=  
		$top		=
		$right		= 
		$bottom		=
		$width		=
		$height		=  false ;

		// Retrieve each entry that allows to specify a coordinate component, using $default_area if needed
		foreach  ( self::$Keys  as  $key )
		   {
			if  ( isset ( $area [ $key ] ) )
			   {
				if  ( $area [ $key ]  ===  false )
				   {
					if  ( isset ( $default_area [ $key ] ) )
						$$key	=  $default_area [ $key ] ;
					else
						$$key	=  false ;
				    }
				else
					$$key	=  $area [ $key ] ;
			    }
			else if  ( isset ( $default_area [ $key ] ) )
				$$key	=  $default_area [ $key ] ;
		    }

		// Check for mandatory coordinates
		if  ( $left  ===  false ) 
			error ( new PdfToTextCaptureException ( "Attribute \"left\" is mandatory." ) );
		else 
			$left	=  ( double ) $left ;

		if  ( $top  ===  false ) 
			error ( new PdfToTextCaptureException ( "Attribute \"top\" is mandatory." ) ) ;
		else 
			$top	=  ( double ) $top ;

		// Either the 'right' or 'width' entries are required
		if  ( $right  ===  false )
		   {
			if  ( $width  ===  false )
				error ( new PdfToTextCaptureException ( "Either the \"right\" or the \"width\" attribute must be specified." ) ) ;
			else 
				$right		=  $left + ( double ) $width - 1 ;
		    }
		else 
			$right	=  ( double ) $right ;

		// Same for 'bottom' and 'height'
		if  ( $bottom  ===  false )
		   {
			if  ( $height  ===  false )
				error ( new PdfToTextCaptureException ( "Either the \"bottom\" or the \"height\" attribute must be specified." ) ) ;
			else 
				$bottom		=  $top - ( double ) $height + 1 ;
		    }
		else 
			$bottom	=  ( double ) $bottom ;

		// All done, we have the coordinates we wanted
		$this -> Left		=  $left ;
		$this -> Right		=  $right ;
		$this -> Top		=  $top ;
		$this -> Bottom		=  $bottom ;
		
		$this -> Name		=  $name ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        __get, __set - Implement the Width and Height properties.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __get ( $member )
	   {
		switch  ( $member ) 
		   {
			case	'Left'		:
			case	'Top'		:
			case	'Right'		:
			case	'Bottom'	:
				return ( $this -> $member ) ;

			case	'Width'		:
				return ( $this -> Right - $this -> Left + 1 ) ;

			case	'Height'	:
				return ( $this -> Top - $this -> Bottom + 1 ) ;

			default :
				trigger_error ( "Undefined property \"$member\"." ) ;
		    }
	    }


	public function  __set ( $member, $value )
	   {
		$value		=  ( double ) $value ;

		switch  ( $member ) 
		   {
			case	'Top'		:
			case	'Left'		:  
			case	'Right'		:
			case	'Bottom'	:
				$this -> $member	=  $value ;
				break ;

			case	'Width'		:
				$this -> Right		=  $this -> Left + $value - 1 ;
				break ;

			case	'Height'	:
				$this -> Bottom		=  $this -> Top - $value + 1 ;
				break ;

			default :
				trigger_error ( "Undefined property \"$member\"." ) ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    NAME
	        Contains - Check if this area contains the specified rectangle.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  Contains ( $left, $top, $right, $bottom )
	   {
		if  ( $left  >=  $this -> Left  &&  $right  <=  $this -> Right  &&
				$top  <=  $this -> Top  &&  $bottom  >=  $this -> Bottom )
			return ( true ) ;
		else
			return ( false ) ;
	    }
    }



/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                                     CAPTURED TEXT MANAGEMENT                                     ******
 ******         (none of the classes listed here are meant to be instantiated outside this file)         ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

 /*==============================================================================================================
 
     class PdfToTextCapturedText -
         Base class for captured text enclosed by shapes.
 
   ==============================================================================================================*/
 abstract class  PdfToTextCapturedText		//extends  Object 
    {
	// Shape name (as specified by the "name" attribute of the <rectangle> or <lines> tags, for example)
	public		$Name ;
	// Number of the page where the text was found (starts from 1)
	public		$Page ;				
	// Shape type (one of the PfToTextCaptureShape::SHAPE_* constants)
	public		$Type ;
	// Shape definition object (not really used, but in case of...)
	private		$ShapeDefinition ;
	// Captured text
	public		$Text ;
	// Surrounding rectangle in the PDF file
	public		$Left,
			$Top,
			$Right,
			$Bottom ;



	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Initializes a captured text object, whatever the original shape.

	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct  ( $page, $name, $text, $left, $top, $right, $bottom, $definition )
	   {
		$this -> Name			=  $name ;
		$this -> Page			=  $page ;
		$this -> ShapeDefinition	=  $definition ;
		$this -> Text			=  $text ;
		$this -> Left			=  $left ;
		$this -> Top			=  $top ;
		$this -> Right			=  $right ;
		$this -> Bottom			=  $bottom ;
		$this -> Type			=  $definition -> Type ;
	    }
     }


 /*==============================================================================================================
 
     class PdfToTextCapturedRectangle -
         Implements a text captured by a rectangle shape.
 
   ==============================================================================================================*/
class  PdfToTextCapturedRectangle		extends  PdfToTextCapturedText
   {
	public function  __construct ( $page, $name, $text, $left, $top, $right, $bottom, $definition )
	   {
		parent::__construct ( $page, $name, $text, $left, $top, $right, $bottom, $definition ) ;
	    }


	public function  __tostring ( )
	   { return ( $this -> Text ) ; }
    }


 /*==============================================================================================================
 
     class PdfToTextCapturedColumn -
         Implements a text captured by a lines/column shape.
	 Actually behaves like the PdfToTextCapturedRectangle class
 
   ==============================================================================================================*/
class  PdfToTextCapturedColumn			extends  PdfToTextCapturedText 
   {
	public function  __construct ( $page, $name, $text, $left, $top, $right, $bottom, $definition )
	   {
		parent::__construct ( $page, $name, $text, $left, $top, $right, $bottom, $definition ) ;
	    }


	public function  __tostring ( )
	   { return ( $this -> Text ) ; }
    }


 /*==============================================================================================================
 
     class PdfToTextCapturedLine -
         Implements a text captured by a lines shape.
 
   ==============================================================================================================*/
class  PdfToTextCapturedLine			extends		PdfToTextCapturedText 
 						implements	ArrayAccess, Countable, IteratorAggregate
  {
	// Column objects
	public		$Columns ;
	// Array of column names, to allow access by either index or column name
	private		$ColumnsByNames		=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Builds a Line object based on the supplied columns.
		Also builds the Text property, which contains the columns text separated by the separator string 
		specified in the XML definition.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $page, $name, $columns, $left, $top, $right, $bottom, $definition )
	   {
		// Although the Columns property is most likely to be used, build a text representation of the whole ine
		$text			=  array ( ) ;
		$count			=  0 ;

		foreach  ( $columns  as  $column )
		   {
			$text []					=  $column -> Text ;
			$this -> ColumnsByNames [ $column -> Name ]	=  $count ++ ;
		    }

		// Provide this information to the parent constructor
		parent::__construct ( $page, $name, implode ( $definition -> Separator, $text ), $left, $top, $right, $bottom, $definition ) ;

		// Store the column definitions
		$this -> Columns	=  $columns ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    __get -
		Returns access to a column by its name.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __get ( $member )
	   {
		if  ( isset ( $this -> ColumnsByNames [ $member ] ) )
			return ( $this -> Columns [ $this -> ColumnsByNames [ $offset ] ] ) ;
		else
			trigger_error ( "Undefined property \"$member\"." ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Interfaces implementations.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( $this -> Columns ) ; }


	public function  getIterator ( )
	   { return ( new ArrayIterator ( $this -> Columns ) ) ; }


	public function  offsetExists ( $offset )
	   { 
		if  ( is_numeric ( $offset ) )
			return ( $offset  >=  0  &&  $offset  <  count ( $this -> Columns ) ) ; 
		else
			return ( isset ( $this -> ColumnsByNames [ $offset ] ) ) ;
	    }


	public function  offsetGet ( $offset )
	   {
		if  ( is_numeric ( $offset ) )
			return ( $this -> Columns [ $offset ] ) ; 
		else
			return ( $this -> Columns [ $this -> ColumnsByNames [ $offset ] ] ) ;
	    }


	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }


	public function  offsetUnset ( $offset )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }
    }


 /*==============================================================================================================
 
     class PdfToTextCapturedLines -
         Implements a set of lines.
 
   ==============================================================================================================*/
class  PdfToTextCapturedLines			//extends		Object 
						implements	ArrayAccess, Countable, IteratorAggregate
   {
	// Capture name, as specified by the "name" attribute of the <lines> tag
	public		$Name ;
	// Page number of the capture
	public		$Page ;
	// Captured lines
	public		$Lines ;
	// Content type (mimics a little bit the PdfToTextCapturedText class)
	public		$Type			=  PdfToTextCaptureShapeDefinition::SHAPE_LINE ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Instantiates a PdfToTextCapturedLines object.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $name, $page, $lines )
	   {
		$this -> Name		=  $name ;
		$this -> Page		=  $page ;
		$this -> Lines		=  $lines ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Interfaces implementations.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( $this -> Lines ) ; }


	public function  getIterator ( )
	   { return ( new ArrayIterator ( $this -> Lines ) ) ; }


	public function  offsetExists ( $offset )
	   { return ( $offset  >=  0  &&  $offset  <  count ( $this -> Lines ) ) ; }


	public function  offsetGet ( $offset )
	   { return ( $this -> Captures [ $offset ] ) ; }


	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }


	public function  offsetUnset ( $offset )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }
    }


/**************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************
 ******                                                                                                  ******
 ******                                                                                                  ******
 ******                               CAPTURE INTERFACE FOR THE DEVELOPER                                ******
 ******         (none of the classes listed here are meant to be instantiated outside this file)         ******
 ******                                                                                                  ******
 ******                                                                                                  ******
 **************************************************************************************************************
 **************************************************************************************************************
 **************************************************************************************************************/

/*==============================================================================================================

    class PdfToTextCaptures -
        Represents all the areas in a PDF file captured by the supplied XML definitions.

  ==============================================================================================================*/
class  PdfToTextCaptures			//extends  Object
   {
	// Captured objects - May not exactly reflect the PdfToTextCapture*Shape classes
	private		$CapturedObjects ;
	// Allows faster access by capture name
	private		$ObjectsByName			=  array ( ) ;


	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Instantiates a PdfToTextCaptures object.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $captures )
	   {
		$this -> CapturedObjects	=  $captures ;

		// Build an array of objects indexed by their names
		foreach  ( $captures  as  $page => $shapes )
		   {
			foreach  ( $shapes  as  $shape )
				$this -> ObjectsByName [ $shape -> Name ] []	=  $shape ;
		    }
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    ToCaptures -
		Returns a simplified view of captured objects, with only name/value pairs.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  ToCaptures ( )
	   {
		$result		=  new stdClass ( ) ;

		foreach  ( $this -> CapturedObjects  as  $page => $captures )
		   {
			foreach  ( $captures  as  $capture )
			    {
				switch  ( $capture -> Type )
				   {
					case	PdfToTextCaptureShapeDefinition::SHAPE_RECTANGLE :
						$name				=  $capture -> Name ;
						$value				=  $capture -> Text ;
						$result -> {$name} [ $page ]	=  $value ;
						break ;

					case	PdfToTextCaptureShapeDefinition::SHAPE_LINE :
						$name				=  $capture -> Name ;

						if  ( ! isset ( $result -> {$name} ) )
							$result -> {$name}		=  array ( ) ;

						foreach  ( $capture  as  $line )
						   {
							$columns	=  new  stdClass ;

							foreach  ( $line  as  $column )
							   {
								$column_name			=  $column -> Name ;
								$column_value			=  $column -> Text ;
								$columns -> {$column_name}	=  $column_value ;
							    }

							$result -> {$name} []	=  $columns ;
						    }
				    }
			    }
		    }

		return ( $result ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    __get -
		Retrieves the captured objects by their name, as specified in the XML definition.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __get ( $member ) 
	   {
		$fieldname	=  "__capture_{$member}__" ;

		if  ( ! isset ( $this -> $fieldname ) )
		   {
			if  ( ! isset ( $this -> ObjectsByName [ $member ] ) )
				error ( new PdfToTextException ( "Undefined property \"$member\"." ) ) ;

			$this -> $fieldname	=  $this -> GetCaptureInstance ( $member ) ;
		    }

		return ( $this -> $fieldname ) ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
	    GetCapturedObjectsByName -
		Returns an associative array of the captured shapes, indexed by their name.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  GetCapturedObjectsByName ( )
	   { return ( $this -> ObjectsByName ) ; }


	/*--------------------------------------------------------------------------------------------------------------
	
	    GetCaptureInstance -
		Returns an object inheriting from the PdfToTextCapture class, that wraps the capture results.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	protected function  GetCaptureInstance ( $fieldname )
	   {
		switch ( $this -> ObjectsByName [ $fieldname ] [0] -> Type )
		   {
			case	PdfToTextCaptureShapeDefinition::SHAPE_RECTANGLE :
				return ( new PdfToTextRectangleCapture ( $this -> ObjectsByName [ $fieldname ] ) ) ;

			case	PdfToTextCaptureShapeDefinition::SHAPE_LINE :
				return ( new PdfToTextLinesCapture ( $this -> ObjectsByName [ $fieldname ] ) ) ;

			default :
				error ( new PdfToTextCaptureException ( "Unhandled shape type " . $this -> ObjectsByName [ $fieldname ] [0] -> Type . "." ) ) ;
		    }
	    }


    }


/*==============================================================================================================

    class PdfToTextCapture -
        Base class for all capture classes accessible to the caller.

  ==============================================================================================================*/
class  PdfToTextCapture				//extends		Object
						implements	ArrayAccess, Countable, IteratorAggregate
   {
	protected	$Captures ;

	
	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Instantiates a PdfToTextCapture object.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $objects )
	   {
		//parent::__construct ( ) ;

		$this -> Captures		=  $objects ;
	    }


	/*--------------------------------------------------------------------------------------------------------------
	
		Interfaces implementations.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  count ( )
	   { return ( $this -> Captures ) ; }


	public function  getIterator ( )
	   { return ( new ArrayIterator ( $this -> Captures ) ) ; }


	public function  offsetExists ( $offset )
	   { return ( $offset  >=  0  &&  $offset  <  count ( $this -> Captures ) ) ; }


	public function  offsetGet ( $offset )
	   { return ( $this -> Captures [ $offset ] ) ; }


	public function  offsetSet ( $offset, $value )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }


	public function  offsetUnset ( $offset )
	   { error ( new PdfToTextCaptureException ( "Unsupported operation." ) ) ; }

    }


/*==============================================================================================================

    class PdfToTextLinesCapture -
        Represents a lines capture, without indexation to their page number.

  ==============================================================================================================*/
class  PdfToTextLinesCapture			extends  PdfToTextCapture
   {
	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		"flattens" the supplied object list, by removing the PdfToTextCapturedLines class level, so that lines
		can be iterated whatever their page number is.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $objects )
	   {
		$new_objects	=  array ( ) ;

		foreach  ( $objects  as  $object )
		   {
			foreach  ( $object  as  $line )
				$new_objects []		=  $line ;
		    }

		parent::__construct ( $new_objects ) ;
	    }
    }


/*==============================================================================================================

    class PdfToTextRectangleCapture -
        Implements a rectangle capture, from the caller point of view.

  ==============================================================================================================*/
class  PdfToTextRectangleCapture		extends  PdfToTextCapture
   {
	/*--------------------------------------------------------------------------------------------------------------
	
	    Constructor -
		Builds an object array indexed by page number.
	
	 *-------------------------------------------------------------------------------------------------------------*/
	public function  __construct ( $objects )
	   {
		$new_objects	=  array ( ) ;

		foreach  ( $objects  as  $object )
			$new_objects [ $object -> Page ]	=  $object ;

		parent::__construct ( $new_objects ) ;
	    }
    }

