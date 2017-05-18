#!/bin/bash

#find . -exec rename_files.bash  {} \;
for file in "$@"
do
	fileto=`php sanitize_file.php "$file"`
	mv $file $fileto
done
