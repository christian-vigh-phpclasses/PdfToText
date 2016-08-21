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

**IMPORTANT** : the **PdfToText** class generates UTF8-encoded text.

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


# KNOWN ISSUES #

Here is a list about known issues for the **PdfToText** class ;  I'm working on solving them, so I hope this whole paragraph will soon completely disappear !

- Unwanted line breaks may occur within text lines. This is due to the fact that the pdf file contains drawing instructions that use relative positioning. This is especially true for file created with generators such as **PdfCreator**. However, some provisions have been made to try to track put text with roughly the same y-coordinates onto the same line
- Encrypted PDF files are not supported 
- Protected regions are not supported. This can pause a problem in text rendering if a referenced font declaration is located in a protected region, because the **PdfToText** class will not be aware that such a font exists, and therefore won't be able to perform character translations because it could not associate a character map with it (by "protected regions", I mean "Binary PDF object data that I really don't know how to interpret"). However, you can still use tools such as *PdfCreator*, *PrimoPdf* or *PDF Pro* to print the PDF file, then run  the **PdfToText** class to extract textual contents, which will this time be fully accessible.

# TESTING #

I have tested this class against dozens of documents from various origins, and tested the output generated from each sample document by the *PdfCreator*, *PrimoPdf* and *PDF Pro* tools.

I also compared the output of the **PdfToText** class with that of *Acrobat Reader*, when you choose the *Save as...Text* option. In many situations, the class performs better in positioning the final text than *Acrobat Reader* does.  

However, all of that will not guarantee that it will work in every situation ; so, if you find something weird or not functioning properly using the **PdfToText** class, feel free to contact me on this class' blog, and send me a sample PDF file at the following email address :

		christian.vigh@wuthering-bytes.com

  
# REFERENCE #

## METHODS ##

### Constructor ###

	$pdf 	=  new PdfToText ( $filename = null, $options = self::PDFOPT_NONE, $user\_password = false, $owner\_password = false ) ;

Instantiates a **PdfToText** object. If a filename has been specified, its text contents will be loaded and made available in the *Text* property (otherwise, you will have to call the *Load()* method for that).

See the *Options* property for a description of the *$options* parameter.

The *$user\_password* and *$owner\_password* parameters specify the user/owner password to be used for decrypting a password-protected file (note that this class is not a password cracker !).

In the current version (1.2.43), decryption of password-protected files is not yet supported.

### Load ( $filename, $user\_password = false, $owner\_password = false ) ###

Loads the text contents of the specified filename.

The *$user\_password* and *$owner\_password* parameters specify the user/owner password to be used for decrypting a password-protected file (note that this class is not a password cracker !).

In the current version (1.2.43), decryption of password-protected files is not yet supported.

### GetPageFromOffset ( $offset ) ###

Given a byte offset in the Text property, returns its page number in the pdf document.

Page numbers start from 1.


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

### BlockSeparator ###

A string to be used for separating chunks of text. The main goal is for processing data displayed in tabular form, to ensure that column contents will not be catenated. However, this does not work in all cases.

The default value is the empty string.

### CreationDate ###

A string containing the document creation date, in UTC format. The value can be used as a parameter to the *strtotime()* PHP function.

### CreatorApplication ###

Application used to create the original document.

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

### EOL ###

The string to be used for line breaks. The default is PHP\_EOL.

### Filename ###

Name of the file whose text contents have been extracted.

### HashedOwnerPassword ###

A 32-byte string used in determining whether a valid owner password was specified.

### HashedUserPassword ###

A 32-byte string used in determining whether a valid user password was specified.

### ID, ID2 ###

A pair of unique ids generated for the document. The value of **ID** is used for decrypting password-protected documents.

The second id is not clearly described in the Pdf specifications.

### Images ###

An array of **PdfImage** objects.

The class currently supports the following properties :

- *ImageResource* : a resource that can be used with the Php *imagexxx()* functions to process image contents.

The following methods are available :

- *SaveAs ( $output\_file, $image\_type = IMG\_JPG )* : Saves the current image to the specified output file, using the specified file format (one of the predefined PHP constants : IMG\_JPG, IMG\_GIF, IMG\_PNG, IMG\_XBMP and IMG\_XBM).

Currently, images stored in proprietary Adobe format are not processed and will not appear in this array.

Note that images will be extracted only if the PDFOPT\_DECODE\_IMAGE\_DATA is enabled. 

### ImageData ###

An array of associative arrays that contain the following entries :

- 'type' : Image type. Can be one of the following :
	- 'jpeg' : Jpeg image type.	Note that in the current version, only jpeg images are processed.
- 'data' : Raw image data.

Note that image data will be extracted only if the PDFOPT\_GET\_IMAGE\_DATA is enabled. 

### IsPasswordProtected ###

This property is set to *true* if the Pdf file is password-protected.

### MinSpaceWidth ###

Sometimes, characters (or blocks of characters) are separated by an offset which is counted in 1/1000's of text units. For certain ranges of values, when displayed on a graphical device, these consecutive characters appear to be separated by one space (or more). Of course, when generating ascii output, we would like to have some equivalent of such spacing.

This is what the *MinSpaceWidth* property is meant for : insert an ascii space in the generated output whenever the offset found exceeds *MinSpaceWidth* text units. 

Note that if the *PDFOPT\_REPEAT\_SEPARATOR* flag is set for the *Options* property, the number of spaces inserted will always be based on a multiple of 1000, even if *MinSpaceWidth* is less than 1000. This means that if *MinSpaceWidth* is 200, and the *Options* property has the *PDFOPT\_REPEAT\_SEPARATOR* flag set, AND the offset between two chunks of characters is 1000 text units, only one space will be inserted, not 5 (which would be the result of 1000/200).

### ModificationDate ###

A string containing the last document modification date, in UTC format. The value can be used as a parameter to the *strtotime()* PHP function.

### Options ###

A combination of the following flags :

- *PDFOPT\_REPEAT\_SEPARATOR* : Sometimes, groups of characters are separated by an integer value, which specifies the offset to subtract to the current position before drawing the next group of characters. This quantity is expressed in thousands of "text units". The **PdfToText** class considers that if this value is less than -1000, then the string specified by the *Separator* property needs to be appended to the result before the next group of characters. If this flag is specified, then the *Separator* string will be appended (*offset* % 1000) times.
- *PDF\_GET\_IMAGE\_DATA* : Store image data from the Pdf file to the **ImageData** array property.
- *PDF\_DECODE\_IMAGE\_DATA* : Decode image data and put it in the **Images** array property.
- *PDFOPT\_IGNORE\_TEXT_LEADING* : This option must be used when you notice that an unnecessary amount of empty lines are inserted between two text elements. This is the symptom that the pdf file contains only relative positioning instructions combined with big values of text leading instructions. 
- *PDFOPT\_NO\_HYPHENATED\_WORDS : When specified, tries to join back hyphenated words into a single word. For example, the following text :

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

- *PDFOPT\_NONE* : Default value. No special processing flags apply.

### OwnerPassword ###

Owner password to be specified if the PDF file is password-protected.

### Pages ###

Associative array containing individual page contents. The array key is the page number, starting from 1.

### PageSeparator ###

String to be used when building the *Text* property to separate individual pages. The default value is a newline.

### ProducerApplication ###

Application used to generate the PDF file contents.

### Separator ###

A string to be used for separating blocks when a negative offset less than -1000 thousands of characters is specified between two sequences of characters specified as an array notation. This trick is often used when a pdf file contains tabular data.

The default value is a space.

### Text ###

A string containing the whole text extracted from the underlying pdf file. Note that pages are separated with a form feed.

### UserPassword ###

User password to be specified if the PDF file is password-protected.

### Utf8Placeholder ###

When a Unicode character cannot be correctly recognized, the Utf8Placeholder property will be used as a substitution.

The string can contain format specifiers recognized by the sprintf() function. The parameter passed to sprintf() is the Unicode codepoint that could not be recognized (an integer value).

The default value is the empty string, or the string '[Unknown character 0x%08X]' when debug mode is enabled.

Note that if you change the *PdfToText::$DEBUG** variable **after** the first instantiation of the class, then you will need to manually set the value of the *PdfToText::Utf8PlaceHolder* static property.

## CONSTANTS ##

### PDFCRYPT\_\* ###

Indicates whether the PDF file is password protected and the encryption mechanism used in this case :

- *PDFCRYPT\_NONE* : file is not password-protected.
- *PDFCRYPT\_STANDARD* : file is password-protected using the standard security handler. 

### PDFOPT\_\* ###

The PDFOPT\_\* constants are a set of flags which can be combined when either instantiating the class or setting the *Options* property before calling the **Load** method. It can be a combination of any of the following flags :

- *PDFOPT\_REPEAT\_SEPARATOR* : Sometimes, groups of characters are separated by an integer value, which specifies the offset to subtract to the current position before drawing the next group of characters. This quantity is expressed in thousands of "text units". The **PdfToText** class considers that if this value is less than -1000, then the string specified by the *Separator* property needs to be appended to the result before the next group of characters. If this flag is specified, then the *Separator* string will be appended (*offset* % 1000) times.
- *PDF\_GET\_IMAGE\_DATA* : Store image data from the Pdf file to the **ImageData** array property.
- *PDF\_DECODE\_IMAGE\_DATA* : Decode image data and put it in the **Images** array property. 
- *PDFOPT\_IGNORE\_TEXT_LEADING* : This option must be used when you notice that an unnecessary amount of empty lines are inserted between two text elements. This is the symptom that the pdf file contains only relative positioning instructions combined with big values of text leading instructions. 
- *PDFOPT\_NONE* : Default value. No special processing flags apply.

### PDFPERM\_\* ###

A set of flags that indicates which operations are authorized on the PDF file. All the descriptions below come from the PDF specification :

- PDFPERM\_PRINT *(bit 3)* : *(Revision 2)* Print the document. *(Revision 3 or greater)* Print the document (possibly not at the highest quality level, depending on whether bit 12 is also set).
- PDFPERM\_MODIFY *(bit 4)* : Modify the contents of the document by operations other than those controlled by bits 6, 9, and 11.
- PDFPERM\_COPY *(bit 5)* : *(Revision 2)* Copy or otherwise extract text and graphics from the document, including extracting text and graphics (in support of accessibility to users with disabilities or for other purposes). *(Revision 3 or greater)* Copy or otherwise extract text and graphics from the document by operations other than that controlled by bit 10.
- PDFPERM\_MODIFY\_EXTRA *(bit 6)* : Add or modify text annotations, fill in interactive form fields, and, if bit 4 is also set, create or modify interactive form fields (including signature fields).
- PDFPERM\_FILL\_FORM *(bit 9)* : *(Revision 3 or greater)* Fill in existing interactive form fields (including signature fields), even if bit 6 is clear.
- PDFPERM\_EXTRACT *(bit 10)* : *(Revision 3 or greater)* Fill in existing interactive form fields (including signature fields), even if bit 6 is clear.
- PDFPERM\_ASSEMBLE *(bit 11)* : *(Revision 3 or greater)* Assemble the document (insert, rotate, or delete pages and create bookmarks or thumbnail images), even if bit 4 is clear.
- PDFPERM\_HIGH\_QUALITY\_PRINT *(bit 12)* : *(Revision 3 or greater)* Print the document to a representation from which a faithful digital copy of the PDF content could be generated. When this bit is clear (and bit 3 is set), printing is limited to a low-level representation of the appearance, possibly of degraded quality. 

### VERSION ###

Current version of the **PdfToText** class, as a string containing a major, minor and release version numbers. For example : "1.2.19".