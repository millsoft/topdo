; wo befindet sich die INput-Datei (In dieser Datei steht der ausgewählte Teil des Quellcodes):
clipfile = D:\htdocs\topdo\data\input.txt

; Output file, the new code will be stored here:
outfile = D:\htdocs\topdo\data\output.txt


;----- IF pressed CTRL+WIN+ALT+7
Capslock::

;---- SEND CTRL+C
Send , ^c

;----- Remove old clip file:
FileDelete,%clipfile%

;----- Insert the contents of the clipoard to the file:
FileAppend,%clipboard%,%clipfile%

;call the parser -> this will generate the "out.txt" file
UrlDownloadToFile, http://localhost:8070/topdo/convert_to_pdo.php, %outfile%

;read the out file to %r% variable:
FileRead, r, %outfile%

;set the clipboard{
Clipboard := r

;paste (ctrl + v)
Send , ^v

;ich habe Fertig ;)

return
