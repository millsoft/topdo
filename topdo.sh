#!/bin/sh
# xdotool /home/m/Scripts/Macros/topdo.script


# xdotool sleep 0.3
xdotool key "ctrl+c"
# xdotool sleep 0.5
xdotool exec xclip -o > /home/m/Dev/topdo/data/input.txt
xdotool sleep 0.5

notify-send topdo topdo started...

# php /home/m/Dev/topdo/convert_to_pdo.php > | xclip -sel clip
php /home/m/Dev/topdo/convert_to_pdo.php | xclip -selection c

xdotool sleep 0.5
xdotool key "ctrl+v"

notify-send topdo DONE!
