#!/bin/bash

#find . -exec rename_files.bash  {} \;
for file in "$@"
do
	fileto=`php sanitize_file.php "$file"`
  if [ "$file" != "$fileto" ] 
  then
    mv "$file" "$fileto";
    echo "$file -> $fileto \n\r"
  fi
done
