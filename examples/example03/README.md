The file *sample.pdf* contains a few opuses from french poets.

It uses a protected font, so from time to time you will see that one sentence is missing (a sentence that references this protected font), or rendered incorrectly with wrong characters.

This is because the *PdfToText* class currently does not handle access to such font information. In fact, the class author (myself) do not have the faintest idea on how to do it !

All I can say is that, even after decoding gzipped contents, I found out that only 3 different fonts were declared. However, more than 10 font resources should be present in the PDF file. They may lie in gzipped PDF objects where you can see the word "Verisign" in them...