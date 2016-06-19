# INTRODUCTION #

I wanted to warmly thank a whole bunch of people here that helped me to enhance my **PdfToText** class. Of course, there is still a lot of work to do, but without their help, I could not have achieved anything reliable.

# INSPIRATIONS #

My first thanks go to the following people :

- The people at Adobe who wrote the Pdf File format reference (I used version 1.7 of this reference, available here : [http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_reference_1-7.pdf](http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_reference_1-7.pdf "http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_reference_1-7.pdf")). As for all specifications and standards, it leaves room for ambiguity, but this is a high quality document that every people concerned with PDF issues should read first (well, as far as you can spen enough time to walk through the 1300 pages that this document contains...)
- The phpclasses.org site, which allowed me to publish this class and provided me with a medium to help new users of my class to solve issues
- The "*unknown developer*" ; when I have been asked for the first time at work to extract text from pdf files, I have been provided with the code referenced here : [contributions/pdftotext.php](contributions/pdftotext.php "contributions/pdftotext.php"). I don't know who this developer is (the source code did not contain any name), but I would like to really thank him, because his works were able to rapidly give me some knowledge of the PDF file format. Although I developed my class my own way, I borrowed from him the **decodeAsciiHex** and **decodeAscii85** functions.
- I also would like to thank Adeel Ahmad Khan whose works gave me further understanding of the Pdf file format ([https://github.com/adeel/php-pdf-parser](https://github.com/adeel/php-pdf-parser "https://github.com/adeel/php-pdf-parser")).

# USERS OF PDFTOTEXT #

When I first published the **PdfToText** class on the *phpclasses.org* site, I already knew that it was not able to handle all the possible situations. The Pdf file format is so versatile that I could not get enough samples to check my class against them.

This is why I clearly asked users to send me sample Pdf files whenever they encountered issues on them, to help me enhance my class ; and every user played the game, so I would like to thank the following people, in completely random order (and hoping that I did not miss anyone of them...) :

- Pawel Lancucki and Blaine Hilton, who helped me to document more precisely on the fact that the class required a PHP version greater or equal to 5.5
- Stephen Layton, who provided me with samples and actively tested my new versions
- Theodis Butler, who also provided me with samples and helped me solve some issues
- Rafael Rojas Torres, who gave me the idea of handling password-protected pdf files. Although not yet implemented, this definitely is on my roadmap.
-  Rolf Kellner , who had issues with unicode translations on far-east and middle-east languages. I have not yet solved them, but I'm still working on it !
-  Yuri Kadeev, who had issues with data presented in tabular format. I won't be able to solve all of them, but it helped me a lot to solve issues presented by other users.
-  Steve Majors, from CashFlowProducts.com, who gave me several samples to work on and even provided me with an access to his bug-tracking system
- Antonio Jùnior, who gave me a sample using text images encoded in a format I did not handle yet ; still under work...
- Tom Perro and the user named *srizoophari*, because they gave me the idea of implementing some features which allow for searching text page by page (and handle page contents separately, instead of a single block of text)

Although I did not solved all the issues yet, I would like to thank you all for your contributions and your help !

