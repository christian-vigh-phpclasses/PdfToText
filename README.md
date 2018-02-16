# INTRODUCTION #

The PdfToText class has been designed to extract textual contents from a PDF file.

It's pretty simple to use :

	include ( 'PdfToText.phpclass' ) ;

	$pdf 	=  new PdfToText ( 'sample.pdf' ) ;
	echo $pdf -> Text ; 		// or you could also write : echo ( string ) $pdf ;

The same PdfToText object can be reused to process additional files :

	$pdf -> Load ( 'sample2.pdf' ) ;
	echo $pdf -> Text ;

Additionally, the **PdfToText** class provides support methods for getting the page number of any text in the underlying PDF file.

Look at the class' blog for an overview on the underlying mechanics that are involved into extracting text contents from pdf files.

Examples are also provided in the **examples/** directory. Please have a look at the [examples/README.md](examples/README.md "README.md") file for a brief explanation on their structure.

**IMPORTANT** : the **PdfToText** class generates UTF8-encoded text. If your default character set is not UTF-8, you may need to add the following **meta** tag in the &lt;head&gt; part of your HTML page :

	<meta charset="utf-8" />


# FEATURES #

Text rendering in a PDF file is made using an obscure language which provides multiple ways to position the same text at the same location on a page. You could say for example :

	. Goto coordinates (x,y)
	. Render text ( "Mail : someone@somewhere.com" )

Or :

	. Goto next line
	. Goto (x1,y)
	. Render text ( "Mail" )
	. Goto (x2, y) 
	. Render text ( ":" )
	. Goto (x3, y)
	. Render text ( "someone@somewhere.com" )

(note that I'm using a pseudo-language here).
Both pieces of code would probably display the same text at the same position, by using rather different ways.

This is why the **PdfToText** class tracks the following information from the drawing-instruction stream to provide more accurate text rendering (even if the output is only pure text) :

- The currently selected font is tracked. This is important because :

	- Each font in a PDF file can have its own character map. This means in this case that characters to be drawn using the Adobe language do not specify actual character codes, but an index into the font's character map.
	- The current font size is memorized ; this helps to evaluate what is the current y-coordinate when relative positioning instructions are used (such as "goto next line"). Although approximative, this works in a great majority of cases
	
- If multiple strings are rendered using identical y-coordinate, they will be grouped onto the same line. Note that they must appear sequentially in the instruction flow for this trick to work
- Sub/super-scripted text is usually written at a slightly different y-coordinate than the line it appears in. Such a situation is detected, and the sub/super-scripted text will correctly appear onto the same line

These symptoms will not appear if the *PDFOPT\_BASIC\_LAYOUT* option is specified.

# ADVANCED FEATURES #

The class is able to :

- Render basic page layout (ie, the text is drawn in the same order that Acrobat Reader renders it) using the *PDFOPT\_BASIC\_LAYOUT* option.
- Retrieve form data as a standalone object, using the *GetFormData()* method.
- Capture areas of text within a page

# KNOWN ISSUES #

Here is a list about known issues for the **PdfToText** class ;  I'm working on solving them, so I hope this whole paragraph will soon completely disappear !

- Unwanted line breaks may occur within text lines. This is due to the fact that the pdf file contains drawing instructions that use relative positioning. This is especially true for file created with generators such as **PdfCreator**. However, some provisions have been made to try to track put text with roughly the same y-coordinates onto the same line. This limitation does not apply if the *PDFOPT\_BASIC\_LAYOUT* option is specified.
- Encrypted PDF files are not supported 

# A NOTE FOR WINDOWS USERS #

An Apache server on Linux platforms allocates a default stack size of 8Mb for its threads. This value is set to 1Mb on Windows platforms.

However, some regular expressions used by the **PdfToText** class may cause the PHP PCRE extension to require a little bit more than 1Mb of stack space when processing certain PDF files.

Such a situation will cause your Windows Apache server to crash and your browser to display a message such as : *Connection reset*. This behavior affect several products such as EasyPHP, XAMPP or Wamp.

To solve this issue, you will have to enable the **mpm** module in your *httpd.conf* file and define a new stack size, as in the following example, given for a Wamp server :

		Include conf/extra/httpd-mpm.conf
		ThreadStackSize 8388608
  


# TESTING #

I have tested this class against dozens of documents from various origins, and tested the output generated from each sample document by the *PdfCreator*, *PrimoPdf* and *PDF Pro* tools.

I also compared the output of the **PdfToText** class with that of *Acrobat Reader*, when you choose the *Save as...Text* option. In many situations, the class performs better in positioning the final text than *Acrobat Reader* does.  

However, all of that will not guarantee that it will work in every situation ; so, if you find something weird or not functioning properly using the **PdfToText** class, feel free to contact me on this class' blog, and/or send me a sample PDF file at the following email address :

		christian.vigh@wuthering-bytes.com

# OTHER LINKS #

This class can also be found here :

[http://www.phpclasses.org/package/9732-PHP-Extract-text-contents-from-PDF-files.html](http://www.phpclasses.org/package/9732-PHP-Extract-text-contents-from-PDF-files.html "http://www.phpclasses.org/package/9732-PHP-Extract-text-contents-from-PDF-files.html")
  
and here, where you will also find a FAQ section and be able to upload your PDF file samples for live testing :

[http://www.pdftotext.eu](http://www.pdftotext.eu "http://www.pdftotext.eu")

and also here :

[https://github.com/christian-vigh-phpclasses/PdfToText](https://github.com/christian-vigh-phpclasses/PdfToText)

# REFERENCE #

## METHODS ##

### Constructor ###

	$pdf 	=  new PdfToText ( $filename = null, $options = self::PDFOPT_NONE, $user\_password = false, $owner\_password = false ) ;

Instantiates a **PdfToText** object. If a filename has been specified, its text contents will be loaded and made available in the *Text* property (otherwise, you will have to call the *Load()* method for that).

See the *Options* property for a description of the *$options* parameter.

The *$user\_password* and *$owner\_password* parameters specify the user/owner password to be used for decrypting a password-protected file (note that this class is not a password cracker !).

In the current version, decryption of password-protected files is not yet supported.

### Load ( $filename, $user\_password = false, $owner\_password = false ) ###

Loads the text contents of the specified filename.

The *$user\_password* and *$owner\_password* parameters specify the user/owner password to be used for decrypting a password-protected file (note that this class is not a password cracker !).

In the current version, decryption of password-protected files is not yet supported.

The method returns the decoded text contents, which are also available through the *Text* property.

### LoadFromString ( $contents, $user\_password = false, $owner\_password = false ) ###

Loads the text contents of the specified PDF contents.

The *$user\_password* and *$owner\_password* parameters specify the user/owner password to be used for decrypting a password-protected file (note that this class is not a password cracker !).

In the current version, decryption of password-protected files is not yet supported.

The method returns the decoded text contents, which are also available through the *Text* property.

### AddAdobeExtraMappings ( $mappings ) ###

Adobe supports 4 predefined fonts : standard, Mac, WinAnsi and PDF). All the characters in these fonts are identified by a character time, a little bit like HTML entities ; for example, 'one' will be the 
character '1', 'acircumflex' will be 'â', etc.

There are thousands of character names defined by Adobe (see [https://mupdf.com/docs/browse/source/pdf/pdf-glyphlist.h.html](https://mupdf.com/docs/browse/source/pdf/pdf-glyphlist.h.html "https://mupdf.com/docs/browse/source/pdf/pdf-glyphlist.h.html")).

Some of them are not in this list ; this is the case for example of the 'ax' character names, where 'x' is a decimal number. When such a character is specified in a /Differences array, then there is somewhere 
a CharProc[] array giving an object id for each of those characters.

The referenced object(s) in turn contain drawing instructions to draw the glyph. At no point you could 
guess what is the corresponding Unicode character for this glyph, since the information is not contained in the PDF file.

The *AddAdobeExtraMappings()* method allows you to specify such correspondences. Specify an array as the *$mappings* parameter, whose keys are the Adobe character name (for example, "a127") and values the 
corresponding Unicode values.

The *$mappings* parameter is an associative array whose keys are Adobe character names. The array values can take several forms :

- A character
- An integer value
- An array of up to four character or integer values. Internally, every specified value is converted to an array of four integer values, one for each of the standard Adobe character sets (Standard, Mac, WinAnsi and PDF). The following rules apply :
	- If the input value is a single character, the output array corrsponding the Adobe character   name will be a set of 4 elements corresponding to the ordinal value of the supplied character.
	- If the input value is an integer, the output array will be a set of 4 identical values
	- If the input value is an array :
	- Arrays with less that 4 elements will be padded, using the last array item for padding
	- Arrays with more than 4 elements will be silently truncated
	- Each array value can either be a character or a numeric value.
	
**Note**
	
In this current implementation, the method applies the mappings to ALL Adobe default fonts. That is,
you cannot have one mapping for one Adobe font referenced in the PDF file, then a second mapping for
a second Adobe font, etc.


### GetCaptures ###

	$captures 	=  $pdf -> GetCaptures ( $full = false ) ;

Retrieves the areas of text captured by the **PdfToText** class. This assumes that you specified first the *PDFOPT\_CAPTURE* flag to the class constructor, then called either the *SetCaptures()* or *SetCapturesFromString()* method.

When the *$full* parameter is set to *false* (the default), the returned object is a hierarchy of **stdClass** objects which maps capture names to their values.

When set to *true*, the returned object is of type **PdfToTextCaptures**, which holds much more information. It should be useful only when doing internal debugging of the PdfToText class.

**Note** : 

Accessing a property value when the *$full* parameter is *false* can be performed like this (here, we are accessing the value of the *Title* capture in the first page) :

	$captures -> Title [1] 

When this parameter is *true*, you have to specify the *Text* property to retrieve its contents :

	$captures -> Title [1] -> Text

If you plan to switch between both return types during your development phase, you can use a unified approach that works in both cases :

	( string ) $captures -> Title [1]  

See the **Capturing Text* section for more information on capturing text from PDF files.

### GetFormCount ###

	$count 		=  $pdf -> GetFormCount ( ) ;

Returns the actual number of top-level forms defined in the PDF file.

### GetFormData ###

	$object 	=  $pdf -> GetFormData ( $template_xml, $index = 0 ) ;

Retrieves the form data for the specified top-level form index. Data is returned as an object inheriting from class **PdfToTextFormData**, which provides ony helper functions to its derived classes. 

Data retrieval can be based on a template XML file, or, when the *$template\_xml* parameter is null, a default template will be created using the field names defined in the PDF file.

See the *Form templates* later in this file to get more information on how templates are used and how form data objects are built. 

### GetPageFromOffset ( $offset ) ###

Given a byte offset in the Text property, returns its page number in the pdf document.

Page numbers start from 1.

### HasFormData ###

	$status 	=  $pdf -> HasFormData ( ) ;

Returns true if the PDF file contains form data.


### MarkTextLike ( $regex, $mark_start, $mark_end ) ###

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


The parameters are the following :	

- *$regex* (string) : A regular expression to match the text to be matched. Subsequent portions of text using the same font will be surrounded by the marker start/end strings.

- *$marker_start*, *$marker_end* (string) : Markers to surround the string when a match is found.


### SetCaptures ###

	$pdf -> SetCaptures ( $xml_file ) ;

Loads the capture definitions from the specified XML file.

Note that you must instantiate the **PdfToText** class with the *PDFOPT\_CAPTURE* flag for this function to work.

See the **Capturing Text* section for more information on capturing text from PDF files.

### SetCapturesFromString ###

	$pdf -> SetCaptures ( $xml_data ) ;

Same as the *SetCaptures()* method, but loads the capture definitions from a string instead of a file.

Note that you must instantiate the **PdfToText** class with the *PDFOPT\_CAPTURE* flag for this function to work.

See the **Capturing Text* section for more information on capturing text from PDF files.

### text\_strpos, text\_stripos ###

    $result		=  $pdf -> text_strpos  ( $search, $start = 0 ) ;
    $result		=  $pdf -> text_stripos ( $search, $start = 0 ) ;

These methods behave as the strpos/stripos PHP functions, except that :

- They operate on the text contents of the pdf file (Text property)
- They return an array containing the page number and text offset. $result [0] will be set to the page number of the searched text, and $result [1] to its offset in the Text property

Parameters are the following :

- *$search* (string) : String to be searched.
- *$start* (integer) : Start offset in the pdf text contents.

The method returns an array of two values containing the page number and text offset if the searched string has been found, or *false* otherwise.

### document\_strpos, document\_stripos ###

    $result		=  $pdf -> document_strpos  ( $search, $group_by_page = false ) ;
    $result		=  $pdf -> document_stripos ( $search, $group_by_page = false ) ;

Searches for ALL occurrences of a given string in the pdf document. The value of the $group_by_page parameter determines how the results are returned :

- When true, the returned value will be an associative array whose keys will be page numbers and values arrays of offset of the found string within the page
- When false, the returned value will be an array of arrays containing two entries : the page number and the text offset.

For example, if a pdf document contains the string "here" at character offset 100 and 200 in page 1, and position 157 in page 3, the returned value will be :

- When *$group\_by\_page* is false :

		[ [ 1, 100 ], [ 1, 200 ], [ 3, 157 ] ]

- When *$group\_by\_page* is true :

		[ 1 => [ 100, 200 ], 3 => [ 157 ]
	
The parameters are the following :

- *$search* (string) : String to be searched.

- *$group\_by\_page (boolean) : Indicates whether the found offsets should be grouped by page number or not.

The method returns an array of page numbers/character offsets or *false* if the specified string does not appear in the document.


### text\_match, document\_match ###

    $status		=  $pdf -> text_match ( $pattern, &$match = null, $flags = 0, $offset = 0 ) ;
    $status		=  $pdf -> document_match ( $pattern, &$match = null, $flags = 0, $offset = 0 ) ;

*text\_match()* calls the preg\_match() PHP function on the pdf text contents, to locate the first occurrence of text that matches the specified regular expression.

*document\_match()* calls the preg\_match\_all() function to locate all occurrences that match the specified regular expression.

Note that both methods add the PREG\_OFFSET\_CAPTURE flag when calling preg\_match/preg\_match\_all so you should be aware that all captured results are an array containing the following entries :

- Item [0] is the captured string
- Item [1] is its text offset
- The *text\_match()* and *document\_match()* methods add an extra array item (index 2), which contains the number of the page where the matched text was found

Parameters are the following :

- *$pattern* (string) : Regular expression to be searched.

- *$match* (any) : Output captures. See preg\_match/preg\_match\_all.

- *$flags* (integer) : PCRE flags. See preg\_match/preg\_match\_all.

- *$offset* (integer) : Start offset. See preg\_match/preg\_match\_all.

As for their PHP counterparts, these methods return the number of matched occurrences, or *false* if the specified regular expression is invalid.

## PROPERTIES ##
 
This section describes the properties that are available in a **PdfTText** object. Note that they should be considered as read-only.

### Author ###

Author name, as inscribed in the PDF file.

### AutoSavedImageFiles ###

When the *PDFOPT\_AUTOSAVE\_IMAGES* flag has been specified, this array of strings will contain the filenames where the auto-saved images have been put.

### BlockSeparator ###

A string to be used for separating chunks of text. The main goal is for processing data displayed in tabular form, to ensure that column contents will not be catenated. However, this does not work in all cases.

The default value is the empty string.

When the *PDFOPT\_BASIC\_LAYOUT* option is specified, this property is used to separate parts of text that are visually on a different x-axis on the same line. In this case, the default separator will be a white space. 

### CIDTablesDirectory ###

Path to the directory containing CID mapping tables.

### CreationDate ###

A string containing the document creation date, in UTC format. The value can be used as a parameter to the *strtotime()* PHP function.

### CreatorApplication ###

Application used to create the original document.

### DocumentStartOffset ###

A PDF document normally starts with a string of the form :

	%PDF-a.b[.c.d...]

where "a" and "b" (and the "c", "d", ... following) represent the version number of the PDF file.

Some PDF documents may come with garbage at the beginning ; this is "illegal" of course, but Acrobat Reader is able to cope with that. So can do the **PdfToText** class...

This property holds the byte offset in the input file where the starting "%PDF" string has been found.


### EncryptionAlgorithm ###

Algorithm used for password-protected files.

The Adobe documentation states :

A code specifying the algorithm to be used in encrypting and decrypting the document :

- 0 : An alternate algorithm that is undocumented and no longer supported, and whose use is strongly discouraged.
- 1 : Algorithm 3.1.
- 2 [PDF 1.4] : Algorithm 3.1, but allowing key lengths greater than 40 bits.
- 3 [PDF 1.4] : An unpublished algorithm allowing key lengths up to 128 bits. This algorithm is unpublished as an export requirement of the U.S. Department of Commerce.

### EncryptionAlgorithmRevision ###

The revision number of the Standard security handler that is required to interpret this dictionary. The revision number is :

- 2 : for documents that do not require the new encryption features of PDF 1.4, meaning documents encrypted with an *EncryptionAlgorithm* value of 1 and using *EncryptionFlags* bits 1– 6
- 3 : for documents requiring the new encryption features of PDF 1.4, meaning documents encrypted with an *EncryptionAlgorithm* value of 2 or greater or that use the extended *EncryptionFlags* bits 17–21.

### EncryptionFlags ###

A set of *PDFPERM\_** constants describing which operations are authorized on a password-protected PDF file.

### EncryptionKeyLength ###

Defined only when *EncryptionAlgorithm* is 2 or 3. Length of key, in bits, used for encryption and decryption. The size is a multiple of 8, with a minimum value of 40 and maximum value of 128.

### EncryptionMode ###

One of the *PDFCRYPT\_\** constants.

This value is set to *PDFCRYPT\_NONE if the PDF file is not password-protected.

### EncryptMetadata ###

A flag coming from a password-protected file that says is the document metadata is also encrypted.

### EOL ###

The string to be used for line breaks. The default is PHP\_EOL.

### ExtraTextWidth ###

This property is expressed in percents ; it gives the extra percentage to add to the values computed by
the **PdfTexterFont::GetStringWidth()** method.

This value is basically used when computing text positions and string lengths with the *PDFOPT\_BASIC\_LAYOUT option : the computed string length is shorter than its actual length (because of extra spacing determined by character kerning in the font data, among other details). 

To determine whether two consecutive blocks of text on the same should be separated by a space, the class will empirically add this extra percentage to the computed string length. The default value is -5 (percent).

### Filename ###

Name of the file whose text contents have been extracted. This value will be an empty string if the
**LoadFromString()** method has been called instead of **Load()**.

### ID, ID2 ###

A pair of unique ids generated for the document. The value of **ID** is used for decrypting password-protected documents.

The second id is not clearly described in the Pdf specifications.

### ImageAutoSaveFileTemplate ###

A template string to be used for generating filenames when the *PDFOPT\_AUTOSAVE\_IMAGES* flag has been specified.

It can contain the following *print*-like formats :

- **%p** : Replaced by the directory where the input PDF file resides.
- **%f** : Replaced by the filename part (without suffix) of the input PDF file.
- **%s** : Replaced by the suffix appropriate to the image format given by the **ImageAutoSaveFormat** property.
- **%d** : Replaced by a sequential value, starting from 1, which gives the image number.

The default value for this property is :

	$ImageAutoSaveFileTemplate	=   "%p/%f.%d.%s" ;

Using the template above, if your input filename is "/tmp/test.pdf" and contains 3 images, then you will get the following output images :

- /tmp/test.1.jpg
- /tmp/test.2.jpg
- /tmp/test.3.jpg

You can also specify a width with the "%d" format specifier ; the numbering will then be padded with leading zeroes. For example, the following template using the same example PDF file as above :

	$pdf -> ImageAutoSaveFileTemplate	=   "%p/%f.%3d.%s" ;

will generate the following filenames :

- /tmp/test.001.jpg
- /tmp/test.002.jpg
- /tmp/test.003.jpg


### ImageAutoSaveFormat ###

When the *PDFOPT\_AUTOSAVE\_IMAGES* flag has been specified, indicates the format to be used for saving the images found in the PDF file. It can be any of the constants defined by the *gd* library regarding image formats : 

			IMG_JPEG		=>  'jpg',
			IMG_JPG			=>  'jpg',
			IMG_GIF			=>  'gif',
			IMG_PNG			=>  'png',
			IMG_WBMP		=>  'wbmp',
			IMG_XPM			=>  'xpm'

Note that the association between the constant and corresponding file suffix is automatically handled.

### Images ###

An array of objects inheriting from the **PdfImage** class. Currently, only the **PdfJpegIMage** class is implemented.

The class currently supports the following properties :

- *ImageResource* : a resource that can be used with the Php *imagexxx()* functions to process image contents.

The following methods are available :

- *SaveAs ( $output\_file, $image\_type = IMG\_JPG )* : Saves the current image to the specified output file, using the specified file format (one of the predefined PHP constants : IMG\_JPG, IMG\_GIF, IMG\_PNG, IMG\_XBMP and IMG\_XBM).
- *Output ( )* : Echoes the image contents to the standard output.

Currently, images stored in proprietary Adobe format are not processed and will not appear in this array.

Note that images will be extracted only if the PDFOPT\_DECODE\_IMAGE\_DATA is enabled. 

### ImageCount ###

Number of images found in the supplied PDF file. This number will only take into account the images whose format is recognized by the **PdfToText** class.

### ImageData ###

An array of associative arrays that contain the following entries :

- 'type' : Image type. Can be one of the following :
	- 'jpeg' : Jpeg image type.	Note that in the current version, only jpeg images are processed.
- 'data' : Raw image data.

Note that image data will be extracted only if the PDFOPT\_GET\_IMAGE\_DATA is enabled. 

### IsEncrypted ###

This property is set to *true* if the Pdf file is encrypted through some kind of password protection scheme.

### Keywords ###

Keywords, as recorded in the author information part.

### MaxExecutionTime ###

Specifies a maximum execution time in seconds for processing a single file. If this limit is reached, a **PdfToTextTimeoutException** exception will be thrown before PHP terminates the script. This allows the script to gracefully handle the error instead of PHP itself.

Positive values are indicated in seconds. Negative values are subtracted from the *max\_execution\_time* PHP setting to compute the maximum execution time allowed.

If the computed timeout value is out of range, the retained execution time will be *max\_execution\_time* minus one second.

The value of this property is taken into account only if the *PDFOPT\_ENFORCE\_EXECUTION\_TIME* option has been specified.

### MaxExtractedImages ###

Maximum number of images to be extracted. The default is the value 0, meaning that all images will be selected for output if the *PDFOPT\_GET\_IMAGE\_DATA* or *PDFOPT\_AUTOSAVE\_IMAGES* option is set.


### MaxGlobalExecutionTime ###

This static property is the same as **MaxExecutionTime**, except that it works globally. If you have to process *x* files, then it will ensure that the global execution time does not exceed the value of this property.

The value of this property is taken into account only if the *PDFOPT\_ENFORCE\_GLOBAL\_EXECUTION\_TIME* option has been specified.
 

### MaxSelectedPages ###

Maximum number of pages to be selected. The default is the value 0, meaning that all pages will be selected for output.

A value of 1 will extract the contents of the first page only, which can be useful if your PDF file is large and you're only interested by the contents of the first page.

When this number is negative, selection starts from the end of the file : -1 means "extract the last page", -2 means "extract the last two pages", and so on.

### MemoryUsage, MemoryPeakUsage ###

Report the memory (peak) used by the *Load()* method.

### MinSpaceWidth ###

Sometimes, characters (or blocks of characters) are separated by an offset which is counted in 1/1000's of text units. For certain ranges of values, when displayed on a graphical device, these consecutive characters appear to be separated by one space (or more). Of course, when generating ascii output, we would like to have some equivalent of such spacing.

This is what the *MinSpaceWidth* property is meant for : insert an ascii space in the generated output whenever the offset found exceeds *MinSpaceWidth* text units. 

Note that if the *PDFOPT\_REPEAT\_SEPARATOR* flag is set for the *Options* property, the number of spaces inserted will always be based on a multiple of 1000, even if *MinSpaceWidth* is less than 1000. This means that if *MinSpaceWidth* is 200, and the *Options* property has the *PDFOPT\_REPEAT\_SEPARATOR* flag set, AND the offset between two chunks of characters is 1000 text units, only one space will be inserted, not 5 (which would be the result of 1000/200).

### ModificationDate ###

A string containing the last document modification date, in UTC format. The value can be used as a parameter to the *strtotime()* PHP function.

### Options ###

A combination of the following flags :

- *PDFOPT\_REPEAT\_SEPARATOR* : Sometimes, groups of characters are separated by an integer value, which specifies the offset to subtract to the current position before drawing the next group of characters. This quantity is expressed in thousands of "text units". The **PdfToText** class considers that if this value is less than -1000, then the string specified by the *Separator* property needs to be appended to the result before the next group of characters. If this flag is specified, then the *Separator* string will be appended (*offset* % 1000) times.
- *PDFOPT\_GET\_IMAGE\_DATA* : Store image data from the Pdf file to the **ImageData** array property.
- *PDFOPT\_DECODE\_IMAGE\_DATA* : Decode image data and put it in the **Images** array property.
- *PDFOPT\_AUTOSAVE\_IMAGES* : Auto-saves the images found in the PDF file, using a filename template given by the **ImageAutoSaveFileTemplate** property. The **ImageAutoSaveFormat** property will define the image format to be used when generating the image files. The default output format is *IMG\_JPEG*. Note that the **Images** property will be left empty. This flag has been introduced to save internal memory if you only need to extract images. 
- *PDFOPT\_IGNORE\_TEXT_LEADING* : This option must be used when you notice that an unnecessary amount of empty lines are inserted between two text elements. This is the symptom that the pdf file contains only relative positioning instructions combined with big values of text leading instructions. 
- *PDFOPT\_RAW\_LAYOUT* : Renders the text as it comes from the PDF file. This may sometimes lead to out-of-order text or strings concatenated in an inappropriate way, but this option is to be preferred if you only need to index contents or focus on performance. This is the default option.
- *PDFOPT\_BASIC\_LAYOUT* : Tries to render the text in the order you can see it with Acrobat Reader. Note that the elements will not be mapped in the output exactly as they appear with Acrobat Reader : elements physically disjoint on the x-axis will be separated by a space by default. The **BlockSeparator** property can be used to modify this separator. The following text for example :

		Company1							Company2
		address1							address2
		city1 								city2

will be rendered as :

		Company1 Company2
		address1 address2
		city1 city2

or, if you set the **BlockSeparator** property to "#", the output will be :

		Company1#Company2
		address1#address2
		city1#city2

- *PDFOPT\_NO\_HYPHENATED\_WORDS* : When specified, tries to join back hyphenated words into a single word. For example, the following text :

		this is a sam-
		ple text using hyphe-
		nated words that can split
		over seve-
		ral lines.
	
will be rendered as :

		this is a sample
		text using hyphenated
		words that can split
		over several lines.

- *PDFOPT\_ENFORCE\_EXECUTION\_TIME* : when specified, the **MaxExecutionTime** property will be checked against the PHP setting *max\_execution\_time*. If the time taken to process a single file may risk to take more time than the value in seconds defined for this property, a **PdfToTextTimeout** exception will be thrown before PHP tries to terminate the script execution.
- *PDFOPT\_ENFORCE\_GLOBAL\_EXECUTION\_TIME* : when specified, the **MaxGlobalExecutionTime** static property will be checked against the PHP setting *max\_execution\_time*. If the time taken to process all PDF files since the start of the script may risk to take more time than the value in seconds defined for this property, a **PdfToTextTimeout** exception will be thrown before PHP tries to terminate the script execution.
- *PDFOPT\_DEBUG\_SHOW\_COORDINATES* : Shows the graphics coordinates before each part of text in the output. This option is useful if you want to define capture areas.
- *PDFOPT\_CAPTURE* : Enables capturing areas of text based on an XML definition file or string. Note that specifying this option will automatically set the *PDFOPT\_BASIC\_LAYOUT* flag.
- *PDFOPT\_NONE* : Default value. No special processing flags apply.

### Pages ###

Associative array containing individual page contents. The array key is the page number, starting from 1.

### PageSeparator ###

String to be used when building the *Text* property to separate individual pages. The default value is a newline.

### ProducerApplication ###

Application used to generate the PDF file contents.

### Separator ###

A string to be used for separating blocks when a negative offset less than -1000 thousands of characters is specified between two sequences of characters specified as an array notation. This trick is often used when a pdf file contains tabular data.

The default value is a space.

### Subject ###

Subject written in the author information part.

### Statistics ###

An associative array that contains the following entries :

- 'TextSize' : Contains the total size in bytes represented by the Postscript-like instructions that draw the document contents
- 'OptimizedTextSize' : Not all Postscript-like instructions for drawing page contents are significant ; since the parsing is done in pure PHP, it is very slow. This is why removing useless instructions with the preg\_replace() builtin function will in most cases significantly reduce the size of the data to be parsed. This entry gives the total size of the data that will be effectively parsed after removing the useless instructions.

### Text ###

A string containing the whole text extracted from the underlying pdf file. Note that pages are separated with a form feed.

### Title ###

Document title, as specified in the author information object.

### Utf8Placeholder ###

When a Unicode character cannot be correctly recognized, the Utf8Placeholder property will be used as a substitution.

The string can contain format specifiers recognized by the sprintf() function. The parameter passed to sprintf() is the Unicode codepoint that could not be recognized (an integer value).

The default value is the empty string, or the string '[Unknown character 0x%08X]' when debug mode is enabled.

Note that if you change the *PdfToText::$DEBUG** variable **after** the first instantiation of the class, then you will need to manually set the value of the *PdfToText::Utf8PlaceHolder* static property.

## CONSTANTS ##

### PDFOPT\_\* ###

The PDFOPT\_\* constants are a set of flags which can be combined when either instantiating the class or setting the *Options* property before calling the **Load** method. It can be a combination of any of the following flags :

- *PDFOPT\_REPEAT\_SEPARATOR* : Sometimes, groups of characters are separated by an integer value, which specifies the offset to subtract to the current position before drawing the next group of characters. This quantity is expressed in thousands of "text units". The **PdfToText** class considers that if this value is less than -1000, then the string specified by the *Separator* property needs to be appended to the result before the next group of characters. If this flag is specified, then the *Separator* string will be appended (*offset* % 1000) times.
- *PDFOPT\_GET\_IMAGE\_DATA* : Store image data from the Pdf file to the **ImageData** array property.
- *PDFOPT\_DECODE\_IMAGE\_DATA* : Decode image data and put it in the **Images** array property.
- *PDFOPT\_AUTOSAVE\_IMAGES* : Auto-saves the images found in the PDF file, using a filename template given by the **ImageAutoSaveFileTemplate** property. The **ImageAutoSaveFormat** property will define the image format to be used when generating the image files. The default output format is *IMG\_JPEG*. Note that the **Images** property will be left empty. This flag has been introduced to save internal memory if you only need to extract images. 
- *PDFOPT\_IGNORE\_TEXT_LEADING* : This option must be used when you notice that an unnecessary amount of empty lines are inserted between two text elements. This is the symptom that the pdf file contains only relative positioning instructions combined with big values of text leading instructions. 
- *PDFOPT\_RAW\_LAYOUT* : Renders the text as it comes from the PDF file. This may sometimes lead to out-of-order text or strings concatenated in an inappropriate way, but this option is to be preferred if you only need to index contents or focus on performance. This is the default option.
- *PDFOPT\_BASIC\_LAYOUT* : Tries to render the text in the order you can see it with Acrobat Reader. Note that the elements will not be mapped in the output exactly as they appear with Acrobat Reader : elements physically disjoint on the x-axis will be separated by a space by default. The **BlockSeparator** property can be used to modify this separator. The following text for example :
 
		Company1							Company2
		address1							address2
		city1 								city2

will be rendered as :

		Company1 Company2
		address1 address2
		city1 city2

or, if you set the **BlockSeparator** property to "#", the output will be :

		Company1#Company2
		address1#address2
		city1#city2

- *PDFOPT\_NO\_HYPHENATED\_WORDS* : When specified, tries to join back hyphenated words into a single word. For example, the following text :

		this is a sam-
		ple text using hyphe-
		nated words that can split
		over seve-
		ral lines.
	
will be rendered as :

		this is a sample
		text using hyphenated
		words that can split
		over several lines.

- *PDFOPT\_ENFORCE\_EXECUTION\_TIME* : when specified, the **MaxExecutionTime** property will be checked against the PHP setting *max\_execution\_time*. If the time taken to process a single file may risk to take more time than the value in seconds defined for this property, a **PdfToTextTimeout** exception will be thrown before PHP tries to terminate the script execution.
- *PDFOPT\_ENFORCE\_GLOBAL\_EXECUTION\_TIME* : when specified, the **MaxGlobalExecutionTime** static property will be checked against the PHP setting *max\_execution\_time*. If the time taken to process all PDF files since the start of the script may risk to take more time than the value in seconds defined for this property, a **PdfToTextTimeout** exception will be thrown before PHP tries to terminate the script execution.
- *PDFOPT\_NONE* : Default value. No special processing flags apply.

### Pages ###

Associative array containing individual page contents. The array key is the page number, starting from 1.

### PageSeparator ###

String to be used when building the *Text* property to separate individual pages. The default value is a newline.


### VERSION ###

Current version of the **PdfToText** class, as a string containing a major, minor and release version numbers. For example : "1.2.19".

## Exceptions ##

The **PdfToText** class can throw any of the following exceptions :

### PdfToTextException ###

This is the base class for all exceptions thrown by the **PdfToText** class.

### PdfToTextDecodingException ###

This exception is thrown if an error occurs when decoding a PDF object. Normally, most of these exceptions are thrown only if debug mode is activated.

### PdfToTextDecryptionException ###

Occurs when decryption failed on an encrypted file.

### PdfToTextTimeoutException ###

This exception is thrown only if one of the following conditions occur :

- The *PDFOPT\_ENFORCE\_EXECUTION\_TIME* option has been set, and processing one file took more time than the number of seconds computed from	the **$MaxExecutionTime** property
- The *PDFOPT\_ENFORCE\_GLOBAL\_EXECUTION\_TIME* option has been set, and processing all the files your script had to process took more than the number of seconds computed from the **$MaxGlobalExecutionTime** static property

### PdfToTextFormTemplateException ###

Thrown when an error is detected while parsing a template file for retrieving form data, or when retrieving form data.

# Form data extraction #

Extracting form data is fairly simple : use the *GetFormData()* method and it will return you an object containing all the field values contained in your PDF file, whether they have been filled or not.

You have two ways to retrieve form data :

- Either by supplying an XML template, that maps actual form field names to more readable names. It provides additional features such as the ability of grouping field values together
- Or by relying on the default behavior, which will return the form field names as they are defined in the PDF file.

Both methods return a new object inheriting from the **PdfToTextFormData** class, which mainly contain helper functions that have no interest for the caller.

The derived class returned by the *GetFormData()* method has a set of properties that give you access to the form fields contents.

The examples given in the following sections are based on the file "sample.pdf", located in the examples directory "examples/formdata-extraction". It has been taken from a very common form used in the US, located here :

[ https://www.irs.gov/pub/irs-pdf/fw9.pdf]( https://www.irs.gov/pub/irs-pdf/fw9.pdf " https://www.irs.gov/pub/irs-pdf/fw9.pdf")

## Getting form data without a template ##

Gettig form data without a template is very simply ; just execute the following code :

	$pdf 		=  new PdfToText ( 'sample.pdf' ) ;
	$form_data	=  $pdf -> GetFormData ( null ) ;

Of course, we should have checked first that form data is present in the PDF file by calling :

	if  ( $pdf -> HasFormData ( ) )
		$form_data	=  $pdf -> GetFormData ( null ) ;

The object returned in *$form\_data* has the following definition :

	$form_data = (object) PdfFormData
	   {
	        protected            $f1_1                            = (string[6]) "ZZNAME"
	        protected            $f1_2                            = (string[14]) "ZZBUSINESSNAME"
	        protected            $c1_1                            = (string[1]) "6"
	        protected            $f1_3                            = (string[1]) "C"
	        protected            $c1_7                            = (string[1]) "7"
	        protected            $f1_4                            = (string[7]) "ZZOTHER"
	        protected            $f1_5                            = (string[4]) "EX01"
	        protected            $f1_6                            = (string[4]) "EX02"
	        protected            $f1_7                            = (string[9]) "ZZADDRESS"
	        protected            $f1_8                            = (string[6]) "ZZCITY"
	        protected            $f1_9                            = (string[28]) "ZZREQUESTERNAME\naddress\ncity"
	        protected            $f1_10                           = (string[16]) "ZZACCOUNTNUMBERS"
	        protected            $f1_11                           = (string[3]) "123"
	        protected            $f1_12                           = (string[2]) "45"
	        protected            $f1_13                           = (string[4]) "6789"
	        protected            $f1_14                           = (string[2]) "EI"
	        protected            $f1_15                           = (string[5]) "ZZEMP"
	    }

You can open file *sample.pdf* to verify that the data listed above is conformant to the one contained in the PDF file.

However, you will see that the field names are not very explicit : *$f1\_1*, *$f1\_2*, etc. Moreover, information such as social security number is splitted in three parts :  *$f1\_11*, *$f1\_12* and *$f1\_13*.

This is why you may want to spend some time designing a template XML file that maps PDF field names to human-readable ones...

## Getting form data with a template ##

Using an XML template does not require many changes to your existing code ; you just need to supply the path of your XML template when calling the *GetFormData()* method :

	$pdf 		=  new PdfToText ( 'sample.pdf' ) ;
	$form_data	=  $pdf -> GetFormData ( 'sample.xml' ) ;
 
Using the supplied example, your *$form\_data* object will look like this :

	$form_data = (object) W9
	   {
	        const  TAXCLASS_INDIVIDUAL                 =  1 ;
	        const  TAXCLASS_C_CORPORATION              =  2 ;
	        const  TAXCLASS_S_CORPORATION              =  3 ;
	        const  TAXCLASS_PARTNERSHIP                =  4 ;
	        const  TAXCLASS_TRUST_ESTATE               =  5 ;
	        const  TAXCLASS_LIMITED_LIABILITY_COMPANY  =  6 ;
	        const  TAXCLASS_UNDEFINED                  = ""  ;
	        const  TAXCLASS_OTHER                      =  7 ;
	
	        protected            $Name                            = (string[6]) "ZZNAME"
	        protected            $BusinessName                    = (string[14]) "ZZBUSINESSNAME"
	        protected            $FederalTaxClassification        = (string[1]) "6"
	        protected            $LLCClassification               = (string[1]) "C"
	        protected            $OtherFederalTaxClassification   = (string[1]) "7"
	        protected            $OtherFederalTaxInfo             = (string[7]) "ZZOTHER"
	        protected            $ExemptPayeeCode                 = (string[4]) "EX01"
	        protected            $FATCAExemptionCode              = (string[4]) "EX02"
	        protected            $Address                         = (string[9]) "ZZADDRESS"
	        protected            $City                            = (string[6]) "ZZCITY"
	        protected            $RequesterCoordinates            = (string[28]) "ZZREQUESTERNAME\naddress\ncity"
	        protected            $AccountNumbers                  = (string[16]) "ZZACCOUNTNUMBERS"
	        protected            $SSN_1                           = (string[3]) "123"
	        protected            $SSN_2                           = (string[2]) "45"
	        protected            $SSN_3                           = (string[4]) "6789"
	        protected            $EIN_1                           = (string[2]) "EI"
	        protected            $EIN_2                           = (string[5]) "ZZEMP"
	        protected            $SSN                             = (string[11]) "123-45-6789"
	        protected            $EIN                             = (string[8]) "EI-ZZEMP"
	    }
	
You can notice some important differences with the templateless version :

- The class name is **W9** instead of **PdfFormData** ; this information comes from the template file
- Constants have been defined ; they also come from the template file 
- Form fields have an explicit name, such as *BusinessName* (instead of *f1\_2*) or *ExemptPayeeCode* (instead of *f1\_5*)
- Two new properties have appeared : *SSN* and *EIN*. In the original form, the social security number is divided into three parts : *f1\_11*, *f1\_12* and *f1\_13*. These fields have been renamed to *SSN\_1*, *SSN\_2* and *SSN\_3* respectively, by the template XML file. But it also defined *SSN* and *EIN*, which are *grouped* properties. *SSN* has been defined to be the concatenation of the *SSN\_1*, *SSN\_2* and *SSN\_3* properties, and *EIN* the concatenation of the *EIN\_1* and *EIN\_2* properties.

All of the above have been defined in the template file, and the parent class, *PdfToTextFormData*, is able to handle any modifications made to any of the properties involved in a grouped property. For example, if you modify the *SSN\_1* property, then the *SSN* property will be rebuilt accordingly. Similarly, if you modify the *SSN* property, then the *SSN\_1*, *SSN\_2* and *SSN\_3* properties will be changed accordingly.

This internal work is performed by the **PdfToTextFormData**, from which any class returned by the *GetFormData()* method inherits.

Now, this is time to have a look at what is a template. This is described in the next section.

## A typical form template ##

Here is the typical form that has been designed to extract form data from our sample PDF file :

	<?xml version="1.0" encoding="utf-8" ?>
	
	<forms class="W9">
		<form version="Form W-9 (Rev. December 2014)">
			<field name="Name"							form-field="f1_1"	type="string"/>
			<field name="BusinessName"					form-field="f1_2"	type="string"/>
			<field name="FederalTaxClassification"		form-field="c1_1"	type="choice">
				<case value="1"		constant="TAXCLASS_INDIVIDUAL"/>
				<case value="2"		constant="TAXCLASS_C_CORPORATION"/>
				<case value="3"		constant="TAXCLASS_S_CORPORATION"/>
				<case value="4"		constant="TAXCLASS_PARTNERSHIP"/>
				<case value="5"		constant="TAXCLASS_TRUST_ESTATE"/>
				<case value="6"		constant="TAXCLASS_LIMITED_LIABILITY_COMPANY"/>
				<default			constant="TAXCLASS_UNDEFINED"/>
			</field>
			<field name="LLCClassification"				form-field="f1_3"	type="string"/>
			<field name="OtherFederalTaxClassification"	form-field="c1_7"	type="choice">
				<case value="7"		constant="TAXCLASS_OTHER"/>
				<default			constant="TAXCLASS_UNDEFINED"/>
			</field>		
			<field name="OtherFederalTaxInfo"			form-field="f1_4"	type="string"/>
			<field name="ExemptPayeeCode"				form-field="f1_5"	type="string"/>
			<field name="FATCAExemptionCode"			form-field="f1_6"	type="string"/>
			<field name="Address"						form-field="f1_7"	type="string"/>
			<field name="City"							form-field="f1_8"	type="string"/>
			<field name="RequesterCoordinates"			form-field="f1_9"	type="string"/>
			<field name="AccountNumbers"				form-field="f1_10"	type="string"/>
	
			<field name="SSN_1"							form-field="f1_11"	type="string"/>
			<field name="SSN_2"							form-field="f1_12"	type="string"/>
			<field name="SSN_3"							form-field="f1_13"	type="string"/>
	
			<field name="EIN_1"							form-field="f1_14"	type="string"/>
			<field name="EIN_2"							form-field="f1_15"	type="string"/>
	
			<group name="SSN" separator="-" fields="SSN_1, SSN_2, SSN_3"/>
			<group name="EIN" separator="-" fields="EIN_1, EIN_2"/>
		</form>
	</forms>

The root tag is specified by *&lt;forms&gt;*. Subtags are *&lt;form&gt;* definitions.

Currently, the following are implemented :

- The *class* attribute of the *&lt;forms&gt;* tag gives the name of the class (inheriting from **PdfToTextFormData**) that will be returned by the *GetFormData()* method, for all the child *&lt;form&gt;* entries.
- Each *&lt;form&gt;* entry has a *version* attribute ; this will be used in the future to handle different versions of the same PDF form.

Please note that the above information could be subject to changes in future releases.

### Defining fields ###

Form fields can currently be of three types :

- String fields
- Choice fields. This is typically used for radiobutton-like checkboxes, which represent a unique field that can have different value, depending on what is checked. Choice fields allow you to associate constants to each individual value.
- Grouped fields. Grouped fields are *virtual* fields that are the result of the concatenation of several existing fields.

A (somewhat) tedious way to get the knowledge of which PDF form field contains which data is :

- Open your PDF form. Fill the values. You may have to test individual values when you are faced with checkboxes (in our above example, properties *FederalTaxClassification* and *OtherFederalTaxClassifications* appear like checkboxes, whose values range from 1 to 7 ; however they have been separated into two fields)
- Make a print_r() of the value returned by the *GetFormData()* method, without specifying any template
- The above steps should help you recognize which field contains what, and design your template accordingly

#### String fields ####

String fields within a form are basically specified with the following XML **field** construct :

		<field name="SSN_1"	form-field="f1_11"	type="string"/>

The attributes are the following :

- **name** : Property name, as it will be present in the class returned by the *GetFormData()* method.
- **form-field** : corresponding name in the PDF form definition. 
- **type** : *string*

#### Choice fields ####

Choice fields are typically used for a group of checkboxes that behave like radiobuttons :

			<field name="FederalTaxClassification"		form-field="c1_1"	type="choice">
				<case value="1"		constant="TAXCLASS_INDIVIDUAL"/>
				<case value="2"		constant="TAXCLASS_C_CORPORATION"/>
				<case value="3"		constant="TAXCLASS_S_CORPORATION"/>
				<case value="4"		constant="TAXCLASS_PARTNERSHIP"/>
				<case value="5"		constant="TAXCLASS_TRUST_ESTATE"/>
				<case value="6"		constant="TAXCLASS_LIMITED_LIABILITY_COMPANY"/>
				<default			constant="TAXCLASS_UNDEFINED"/>
			</field>

They basically contain the same information as *string* fields, except that the *type* attribute is set to **choice**.

They can be paired to a set of constants defined either through the *&lt;case&gt;* or *&lt;default&gt;* tags :

- *&lt;case&gt;* tags allow you to associate a named constant with a specific value
- *&lt;default&gt;* allow you to associate a named constant when none of the *&lt;case&gt;* tags defined matches the form value.

Note that each defined constant, such as *TAXCLASS\_INDIVIDUAL* in the above example, will be defined as a class constant in the object returned by the *GetFormData()* method.

#### Grouped fields ####

Grouped fields allow you to create new properties, coming from the concatenation of existing fields. A typical definition looks like this :

		<group name="SSN" separator="-" fields="SSN_1, SSN_2, SSN_3"/>

In our template example, we have seen that the social security number (SSN) was splitted into three parts : *SSN\_1*, *SSN\_2* and *SSN\_3*. The above example creates an *SSN* property, which is the result of the concatenation of the specified fields.

The required attributes are the following :

- *name* : Name of the grouped property
- *fields* : A comma-separated list of existing field names that should be grouped together
- *separator* : Separator string used to separate each component of the grouped field.

Note that modifying the value of a property referenced by a grouped field will modify the corresponding grouped field value. Similarly, modifying the grouped field value will modify its associated properties.


# Capturing text #

Sometimes, it's easier to tell the **PdfToText** class which area(s) of text you want to retrieve from which page(s), rather than having to struggle with regular expressions to isolate the information you want. This is especially true when you want to retrieve data from tabular reports.

Captures are a solution for such needs ; they allow you to define shapes of the following types :

- *Rectangles* : rectangles are used to surround areas of text whose contents you want to extract after processing.
- *Lines* : allow you to capture lines (and columns within lines) when you have to process a report presented in tabular format.

Areas to be captured are specified using a capture definition file or string, in XML format.

## A step-by-step overview ##

Capturing areas of your PDF document will require you a few preliminary steps that involve some extra work. This is why you will have to choose between using captures or using regular expressions on the extracted text :

- If you have several documents of the same type (reports, for example), then it makes sense to spend some time designing a capture definition file than will allow to easily retrieve line/column values 
- If you have many documents with many different presentations, then regular expressions will be of better help for you

The step-by-step overview below references the files located in the examples/text-captures directory.

### Determining what to capture ###

A PDF file uses a coordinate system whose values are more or less expressed in "relative units". The point at coordinates (0,0) is located at the bottom-left corner of the page ; the point at coordinates (x,y), where "x" is the page width and "y" the page height, is located at the top-right corner of the page.

That's nice, but how to find the coordinates (x, y, width and height) of the rectangle that contains the text you want to capture ? of course, if you have an Adobe product that allows you to modify PDF files, it may give you such information. But if you don't have such a tool, you're stuck.

The **PdfToText** class has a flag, *PDFOPT\_DEBUG\_SHOW\_COORDINATES*, that includes in the text output the (x,y) coordinates, width and height of each block of text found in the PDF stream. This may be the only occasion you will have to use this option. The following example script explains how the file *sample-report.txt* in the *examples/text-capture* directory of this package was generated :

	<?php
			include ( 'path/to/PdfToText.phpclass' ) ;

			$pdf	=  new PdfToText ( 'sample-report.pdf', PDFOPT_DEBUG_SHOW_COORDINATES ) ;
			file_put_contents ( 'sample-report.txt', $pdf -> Text ) ;

The first few lines of file *sample-report.txt* now contain things like this :

	[Page : 1, width = 596, height = 843]
	
	[x:248.76, y:760.4, w: 79.895, h:12]REPORT HEADER 
	
	[x:70.695, y:746.6, w: 2.381, h:12] 
	
	[x:84.495, y:722.6, w: 2.381, h:12] 
	[x:84.495, y:734.6, w: 2.381, h:12] 
	
	[x:0, y:708.08, w: 124.619, h:12]Column1  Column2  Column3 
	
	[x:70.8, y:690.32, w: 76.99, h:12]L1C1  L1C2  L1C3 
	
	[x:70.8, y:676.04, w: 76.99, h:12]L2C1  L2C2  L2C3 
	
	[x:70.8, y:661.76, w: 76.99, h:12]L3C1  L3C2  L3C3 
	
	[x:70.8, y:647.48, w: 76.99, h:12]L4C1  L4C2  L4C3 
	
	[x:70.8, y:633.2, w: 2.381, h:12] 
	[Page : 2, width = 596, height = 843]
	
	[x:70.8, y:760.4, w: 2.381, h:12] 
	
	[x:0, y:745.88, w: 124.619, h:12]Column1  Column2  Column3 
	
	[x:70.8, y:731.72, w: 178.533, h:12]L1C1 (page 2)  L1C2 (page 2)  L1C3 (page 2) 
	
	[x:70.8, y:717.44, w: 178.533, h:12]L2C1 (page 2)  L2C2 (page 2)  L2C3 (page 2) 
	
	[x:70.8, y:703.16, w: 178.533, h:12]L3C1 (page 2)  L3C2 (page 2)  L3C3 (page 2) 
	
	[x:70.8, y:688.88, w: 178.533, h:12]L4C1 (page 2)  L4C2 (page 2)  L4C3 (page 2) 
	
	[x:70.8, y:674.6, w: 2.381, h:12]

instead of the default output with no specific options (see file *sample-report.pdf* for its graphic counterpart) :

	REPORT HEADER 
	 
	Column1  Column2  Column3 
	L1C1  L1C2  L1C3 
	L2C1  L2C2  L2C3 
	L3C1  L3C2  L3C3 
	L4C1  L4C2  L4C3 
	 
	Column1  Column2  Column3 
	L1C1 (page 2)  L1C2 (page 2)  L1C3 (page 2) 
	L2C1 (page 2)  L2C2 (page 2)  L2C3 (page 2) 
	L3C1 (page 2)  L3C2 (page 2)  L3C3 (page 2) 
	L4C1 (page 2)  L4C2 (page 2)  L4C3 (page 2) 
  
In the output generated with the *PDFOPT\_DEBUG\_SHOW\_COORDINATES* option, you will notice some additional information between square brackets :

- The first one appears at the start of each page :

		[Page : 1, width = 596, height = 843]
		...
		[Page : 2, width = 596, height = 843]
		...

It gives the page number, its width and its height in graphics coordinates.

- The second kind of information that appears between square brackets gives size information regarding the block of text immediately following it :

		[x:248.76, y:760.4, w: 79.895, h:12]REPORT HEADER 

### Designing the capture definitions file (or string) ###

The various information added to the output using the *PDFOPT\_DEBUG\_SHOW\_COORDINATES* option should give you enough data to design the capture definitions file.

An example is given below, which will capture some contents of the PDF file *sample-report.pdf* ; a more detailed explanation of this XML format is given in the **XML Capture Definitions** section :

	<captures>
		<rectangle name="Title">
			<page number="1" left="245" right="360" top="760" bottom="740"/>
		</rectangle>

		<lines name="ReportLines" default="*UNDEFINED*" separator="\n">
			<page number="1"	top="690" height="16" bottom="0"/>
			<page number="2..$"	top="731" height="16" bottom="0"/>
	
			<column name="Column1"		left="70"	width="600"/>
		</lines>
	</captures>

Basically, the above data says that it wants to capture two things :

- A *&lt;rectangle&gt;*, located on page 1, and named "Title". The specified coordinates (left, top, right, bottom - but you can also specify width or height instead of right and bottom) define the rectangle that contains the string "REPORT HEADER" in the PDF file
- A *&lt;lines&gt;* tag named "ReportLines" that defines which columns are to be captured from the input PDF stream. This is done by using the *&lt;column&gt;* tags, which give the x-position and individual width of every column you want to capture.

You may have noticed that both of the *&lt;rectangle&gt;* and *&lt;lines&gt;* contain subtags named *&lt;page&gt;* ; they define which pages of the input PDF file are applicable to the parent tag. For example :

- The rectangle we want to capture is only defined on page 1 of the PDF file
- The group of lines we want to capture start at y-position 690, and 731 for page 2 until the last page ($). Line heights are 16 in both cases. Inside this group of lines, you can specify as many columns you want to capture (in our example, only one column is defined ; its name is "Column1").

All the coordinates, widths and heights listed in this definition have been taken from the information contained in file  *sample-report.txt*, generated at the previous step (the above section).
 
### Setting up PdfToText to process captures ###

Captures are processed independently of text extraction ; the only two things you have to do is :

- Specify the *PDFOPT\_CAPTURE* in the Options flags
- Call either the *SetCaptures()* or the *SetCapturesFromString()* method to load the capture definitions, as in the following example :


	$pdf 		=  new PdfToText ( 'sample-report.pdf', PdfToText::PDFOPT_CAPTURE ) ;
	$pdf -> SetCaptures ( 'sample-report.xml' ) ;

### Retrieving capture data ###

Retrieving capture data is fairly simple :

	$captures 	=  $pdf -> GetCaptures ( ) ;

This will return an object of type **PdfToTextCaptureLines**.

Each tag that has been defined in the XML definition file (*sample-report.txt* in our example) has a name ; for example, our rectangle shape has the name "Title", and the group of lines is named "ReportLines" (each report line having one column named "Column1").

Every capture name specified in the Capture definitions file can be accessed as a property name in the object returned by the *GetCaptures()* method :

	$capture -> Title
	$capture -> ReportLines

However, the way these various informations are processed depend on the capture type :

- For Rectangle captures, the property will be an array, since the same capture can be defined on different pages at different locations. To retrieve the Title located on the first page of our PDF file, we will have to write :

		$capture -> Title [1] -> Text 

or :

		( string ) $capture -> Title [1] 

Rectangle captures are accessible by their page number. There will be a capture for each page of the document, even if not present in the list of applicable pages.

- For lines captures, the situation is a little bit different : the interest of capturing report lines (together with their column data) is to be able to process them all at once. This is why they are grouped together in a single collection, that you can display or process with such kind of loops :

		foreach ( $captures -> ReportLines  as  $line )
		   {
				foreach  ( $line  as  $column )
					// do something with $column -> Text
		    }

Inside a line, you can also reference a column by its name :

		$line -> Column1 -> Text

## XML Capture definitions ##

This section describes the format of an XML Capture definition.

### &lt;captures&gt; tag ###

The &lt;captures&gt; tag is the top-level node for the XML Capture definitions. It has no specific attributes and can contain one or more of the following subtags :

- &lt;rectangle&gt;
- &lt;lines&gt;

### &lt;rectangle&gt; tag ###

The &lt;rectangle&gt; tag defines a rectangular area used to capture text enclosed by it. It has the following attributes :

- *name* : Name of the shape. Must be a valid PHP identifier that will be used to access this property information from the object returned by the *GetCaptures()* method. This name must be unique.

The  &lt;rectangle&gt; tag must specify at least one &lt;page&gt; subtag, to indicate the **PdfToText** class on which pages of the document the captured text should be searched.

#### &lt;page&gt; subtag ####

The &lt;page&gt; subtag of the &lt;rectangle&gt; tag has the following attributes :

- *number* : Page number(s). This can be a comma-separated list of pages or ranges of pages separated by an ellipsis, as in the following example :


		"1,2"
		"1,2..10"

The special character "**$**" means "the last page".

Expressions are allowed ; the following for example :

		"1, $-9..$"

indicates that the rectangle area applies to page 1 and to the last 10 pages of the document. 

The other attributes for the &lt;page&gt; tag are the following :
 
- *left* : left x-coordinate of the rectangle.
- *right* : right x-coordinate.
- *top* : top y-coordinate.
- *bottom* : bottom y-coordinate.
- *height* : Specifies the rectangle height. One of the following combinations of attributes must be specified : ($top, $height), ($bottom, $height) or ($top, $bottom)
- *width* : Specifies the rectangle width. One of the following combinations of attributes must be specified : ($left, $width), ($right, $width) or ($left, $right).
 
### &lt;lines&gt; tag ###

The &lt;lines&gt; tag allows to define a group of lines to be captured. It defines applicable pages, and provides definitions about each column to be captured. It is mainly used for capturing report lines and has the following attributes :

- *name* : Element name. Must be a valid PHP identifier that will be used to access this property information from the object returned by the *GetCaptures()* method. This name must be unique.
- *default* : Provides a default value for columns that have no captured text inside the PDF document. 
- *separator* : Each line contains a *Text* property that represents all the columns of the lines, separated by this attribute value.

The *&lt;lines&gt;* tag can contain as many *&lt;page&gt;* and *&lt;column&gt;* subtags as needed.

#### &lt;page&gt; subtag ####

The *&lt;page&gt;* subtag of the *&lt;lines&gt;* tag has more or less the same objective as the *&lt;page&gt;* subtag of the *&lt;rectangle&gt;* tag, with a few differences although. It is not used to define the shape of a rectangle, but rather the top and bottom coordinates of the first and last line of a page, along with the line height. 

The following attributes are available :

- *number* : Page number(s). It has the same syntax as for the *&lt;page&gt;* subtag of the *&lt;rectangle&gt;* tag
- *top* : the y-coordinate of the first line to be captured on the page
- *bottom* : the y-coordinate of the last line to be captured on the page. The default is 0.
- *height* : line height.

#### &lt;column&gt; subtag ####

The &lt;column&gt; subtag identifies a column position within a line. It has the following attributes :

- *name* : Column name. This name must be a valid PHP  identifier that will be used to access this property information from the object returned by the *GetCaptures()* method. Column names must be unique within a &lt;lines&gt; tag.
- *default* : default value to be put in the capture if no information has been found in this line/column combination. If not specified, the value of the "*default*" attribute of the &lt;lines;&gt; will be used. If not specified, the default value will be the empty string.
- *left* : first x-position of the column
- *width* : column width
- *right* : last x-position of the column.

Either the *width* or *right* attribute must be specified.


## Capture classes reference ##

The object returned by the *GetCaptures()* method needs some explanation ; a distinction has been made between *what* is captured and *how* to access it.

The *GetCaptures()* method returns an object hierarchy that answers the *how* ; every object in this hierarchy contains objects that answer the *what*. The method returns an object of class **PdfToTextCaptures**, described below. 

### PdftoTextCaptures class ###

This class dynamically creates properties coming directly from the names specified with the &lt;*rectangle*&gt; and &lt;*lines*&gt; tags of the Capture XML definitions.

Thus, if we take our example definition file and call the *GetCaptures()* method :

	 $captures 	=  $pdf -> GetCaptures ( ) ;

Then we will be able to access the properties defined in our capture file in the following way :

	$captures -> Title

or : 

	$captures -> ReportLines

Here is the correspondance between Capture XML definitions and the properties found in this object :

- A &lt;*rectangle*&gt; tag will give a property with the same name as the one specified in the *name* attribute of the tag. This property is of type **PdfToTextRectangleCapture**. This is an array property whose indexes are the page numbers containing the captures. Note that there will be an entry for each page of the document, even if empty or if not specified in the &lt;*page*&gt; tag of the &lt;*rectangle*&gt; entry. 
- The same principle applies for &lt;*lines*&lt;, which gives a property of type **PdfToTextLinesCapture**.  


### PdfToTextRectangleCapture class ###

This class allows to retrieve information about the text captured based on a &lt;*rectangle*&gt; definition. It behaves like an array containing elements of type **PdfToTextCapturedRectangle**.

The reason why this class is an array is because the *rectangle* shape can be used to capture text at different locations on different pages. 

### PdfToTextLinesCapture class ###

This class also behaves as an array that gives elements of type **PdfToTextCaptureLine**. It contains the lines that have been captured on the whole document.

### PdfToTextCapturedText class ### 

This is the base abstract class for the various shapes captured in the document (PdfToTextCapturedRectangle, etc.). It has the following properties :

- *Name* : capture name
- *Page* : page number where the captured text 
- *Text*	 : captured text
- *Left*, *Right*, *Top*, *Bottom* : coordinates of the captured text

### PdfToTextCapturedRectangle class ###

Inherits from the **PfToTextCapturedText** class. It currently does not add any new properties or methods.

### PdfToTextCapturedLine class ###

Inherits from the **PfToTextCapturedText** class. Adds the following properties :

- *Columns* : An array of **PdfToTextCapturedColumn** objects.

An object of this class can be iterated like an array. It also provides direct access to a **PdfToTextCapturedColumn** object by specifying its name as a property ; for example :

	$captured_line -> Column1 

Of course, the column name has been specified in the Capture XML definition, with the *name* attribute of the &lt;*column&gt;* tag.
