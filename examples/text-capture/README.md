This example shows you how to capture text areas and table lines/columns from a PDF document.

The directory includes the following files :

- *sample-report.pdf* : the sample PDF file used in this example.
- *sample-report.doc* : the original Microsoft Word document that was used to generate *sample-report.pdf*
- *sample-report.xml* : the Capture definitions file that specifies what is to be captured (in XML format)
- *example.php* : the PHP script that takes as input *sample-report.pdf* and *sample-report.xml* to extract only the information you want
- *sample-report.txt* : the output of a previous run of the PdfToText class against file *sample-report.pdf*, with the *PDFOPT\_DEBUG\_SHOW\_COORDINATES* option. It gives every block of text found in the input document, with its (x,y) coordinates and width/height. This information is really useful when you have to design a Capture definitions file because it requires such information.

This example may not be the best for you, because in the current version (1.6.0), all the columns in file *sample-report.pdf* are interpreted as a single column. This issue will be fixed in a future release, probably 1.6.1
