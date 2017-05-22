#!/bin/bash          
DD=$(date +%d -d "1 day ago")
MM=$(date +%m -d "1 day ago")
YYYY=$(date +%Y -d "1 day ago")
#DD=$(date +%d)  #this is for the same day, uncomment me if we're pushin for real
#MM=$(date +%m)
#YYYY=$(date +%Y 

FILES_DIR='/home/bbreedlove/code/wp-migration/imgs/'

read -sp "Pastina's sql Password: " SQL_PASS
if [[ -z "$SQL_PASS" ]]; then
echo "Whoops, need password"
exit
fi

echo "\n"

scp -r  motherjones_d6.prod@web-197.prod.hosting.acquia.com:/mnt/files/motherjones_d6/backups/prod-motherjones_d6-motherjones_d6-$YYYY-$MM-$DD.sql.gz . 

echo "Prod db backup downloaded"
echo "\n"


gunzip prod-motherjones_d6-motherjones_d6-$YYYY-$MM-$DD.sql.gz

echo "Prod db unzipped"
echo "\n"


mysql -u root -p"$SQL_PASS" mjd6 < prod-motherjones_d6-motherjones_d6-$YYYY-$MM-$DD.sql

echo "prod db pushed into database"
echo "\n"

php migrate_links.php "$SQL_PASS" #redirects must be in place for toc pages in migrate, below

echo "redirects added to wp db"
echo "\n"


php migrate_database.php "$SQL_PASS"

echo "most stuff addded to wp db"
echo "\n"


php migrate_blocks.php "$SQL_PASS"

echo "blocks added to wp db"
echo "\n"

php migrate_options.php "$SQL_PASS"

echo "options updated in wp db"
echo "\n"

php migrate_testing_data.php "$SQL_PASS"

echo "test users added to wp db"
echo "\n"

php migrate_story_stubs.php "$SQL_PASS"

echo "page stubs added to wp db"
echo "\n"

php migrate_menus.php "$SQL_PASS"

echo "menu items added to wp db"
echo "\n"

mysqldump -u root -p"$SQL_PASS" pantheon_wp > migrated-wp-db-$YYYY-$MM-$DD.sql

echo "wp db dumped to file at migrated-wp-db-$YYYY-$MM-$DD.sql"
echo "\n"


rsync -rtvzl --ignore-existing --chmod=ug+rwX -e "ssh -i /home/bbreedlove/.ssh/id_rsa" \
  --exclude=/boost* --exclude=/cache-tmpfs* --exclude=/js* \
  --exclude=/css*  \
  --exclude=/imagecache*  \
  --exclude=*.html  \
  --exclude=*.xml  \
  --exclude=/cloudfront_queue*  \
  --exclude=/imagefield_thumbs*  \
  --exclude=*.smallthumb.*  \
  --exclude=*.thumbnail.*  \
  --exclude=*.author_profile.*  \
  --exclude=*.img_assist_properties.*  \
  --exclude=*.popup.*  \
  --exclude=*.preview.*  \
  --exclude=*.img_assist_custom-*  \
  --exclude=*.LCK  \
  --exclude=/resized*  \
  motherjones_d6.prod@web-197.prod.hosting.acquia.com:/mnt/files/motherjones_d6/files/ \
  $FILES_DIR

find $FILES_DIR -exec ./rename_files.bash  {} \;

tar -zcvf files-$YYYY-$MM-$DD.sql.gz $FILES_DIR
