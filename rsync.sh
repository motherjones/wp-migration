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
  /home/bbreedlove/code/wp-migration/imgs/
  #$FILES_DIR
