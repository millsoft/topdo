;----- IF pressed CTRL+WIN+ALT+7
;----- mapped with "7" macro key on my mouse

^!#7::
;press CTRL+C
Send , ^c
clipfile = I:\michel.pits\httpdocs\topdo\data\input.txt
outfile = I:\michel.pits\httpdocs\topdo\data\output.txt
tmpfile = I:\michel.pits\httpdocs\topdo\data\tmp.txt

FileDelete,%clipfile%
FileAppend,%clipboard%,%clipfile%

;call the parser -> this will generate the "out.txt" file
UrlDownloadToFile, http://michel.pits/topdo/convert_to_pdo.php, %outfile%

;read the out file to %r% variable:
FileRead, r, %outfile%

;set the clipboard{
Clipboard := r

;paste (ctrl + v)
Send , ^v

;some sound
SoundPlay *64

return

