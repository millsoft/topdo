;----- IF pressed CTRL+WIN+ALT+7
;----- mapped with "7" macro key on my mouse

^!#7::
;press CTRL+C
Send , ^c
clipfile = D:\htdocs\topdo\data\input.txt
outfile = D:\htdocs\topdo\data\output.txt
tmpfile = D:\htdocs\topdo\data\tmp.txt

FileDelete,%clipfile%
FileAppend,%clipboard%,%clipfile%

;call the parser -> this will generate the "out.txt" file
UrlDownloadToFile, http://localhost:8070/topdo/convert_to_pdo.php, %outfile%

;read the out file to %r% variable:
FileRead, r, %outfile%

;set the clipboard{
Clipboard := r

;paste (ctrl + v)
Send , ^v

;some sound
SoundPlay *64

return

