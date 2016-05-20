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

	$pdf 	=  new PdfToText ( $filename = null ) ;

Instantiates a **PdfToText** object. If a filename has been specified, its text contents will be loaded and made available in the *Text* property (otherwise, you will have to call the *Load()* method for that).

### Load ( $filename ) ###

Loads the text contents of the specified filename.


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
- The *text\_match()* and *document\_match()* methods add an extra array item (index 2), which contains the number of the page where the matched text resides

Parameters are the following :

- *$pattern* (string) : Regular expression to be searched.

- *$match* (any) : Output captures. See preg\_match/preg\_match\_all.

- *$flags* (integer) : PCRE flags. See preg\_match/preg\_match\_all.

- *$offset* (integer) : Start offset. See preg\_match/preg\_match\_all.

As for their PHP counterparts, these methods return the number of matched occurrences, or *false* if the specified regular expression is invalid.
