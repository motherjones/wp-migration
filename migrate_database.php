<?php

#ini_set('memory_limit', '-1');
#$hostname="127.0.0.1";
$hostname="localhost";
$username="root";
$password=$argv[1];
$d6_db = "mjd6";
$wp_db = "pantheon_wp";
$FILEDIR_ABS = "http://dev-mjwordpress.pantheonsite.io/wp-content/uploads/";
$FILEDIR = "";


$wp = new PDO("mysql:host=$hostname;dbname=$wp_db", $username, $password);

$d6 = new PDO("mysql:host=$hostname;dbname=$d6_db", $username, $password);

// Yanked directly from wp
function sanitize_file_name( $filename ) {
	$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", chr(0));
	$filename = preg_replace( "#\x{00a0}#siu", ' ', $filename );
	$filename = str_replace( $special_chars, '', $filename );
	$filename = str_replace( array( '%20', '+' ), '-', $filename );
	$filename = preg_replace( '/[\r\n\t -]+/', '-', $filename );
	$filename = trim( $filename, '.-_' );

	if ( false === strpos( $filename, '.' ) ) {
		$mime_types = wp_get_mime_types();
		$filetype = wp_check_filetype( 'test.' . $filename, $mime_types );
		if ( $filetype['ext'] === $filename ) {
			$filename = 'unnamed-file.' . $filetype['ext'];
		}
	}

	// Split the filename into a base and extension[s]
	$parts = explode('.', $filename);

	// Return if only one extension
	if ( count( $parts ) <= 2 ) {
		return $filename;
	}

	// Process multiple extensions
	$filename = array_shift($parts);
	$extension = array_pop($parts);
	$mimes = get_allowed_mime_types();

	/*
	 * Loop over any intermediate extensions. Postfix them with a trailing underscore
	 * if they are a 2 - 5 character long alpha string not in the extension whitelist.
	 */
	foreach ( (array) $parts as $part) {
		$filename .= '.' . $part;

		if ( preg_match("/^[a-zA-Z]{2,5}\d?$/", $part) ) {
			$allowed = false;
			foreach ( $mimes as $ext_preg => $mime_match ) {
				$ext_preg = '!^(' . $ext_preg . ')$!i';
				if ( preg_match( $ext_preg, $part ) ) {
					$allowed = true;
					break;
				}
			}
			if ( !$allowed )
				$filename .= '_';
		}
	}
	$filename .= '.' . $extension;
	/** This filter is documented in wp-includes/formatting.php */
	return $filename;
}

function deleteNode($node) {
	deleteChildren($node);
	$parent = $node->parentNode;
	return $parent->removeChild($node);
}

function deleteChildren($node) {
	while (isset($node->firstChild)) {
		deleteChildren($node->firstChild);
		$node->removeChild($node->firstChild);
	}
}

function fix_post_body($html) {
	if (!$html) {return;}
	$dom = new DOMDocument;
	$dom->loadHTML($html);
	$doc = $dom->documentElement;
	$divs = $doc->getElementsByTagName('div');
	foreach ($divs as $div) {
		if (!$div->hasAttribute('data-episode-id')) { continue; }
		$remove_list = Array();
		$scripts = $doc->getElementsByTagName('script');
		foreach ($scripts as $script) {
			$remove_list []= $script;
		}
		$styles = $doc->getElementsByTagName('link');
		foreach ($styles as $style) {
			$remove_list []= $style;
		}
		foreach ($remove_list as $node) {
			deleteNode($node);
		}
		$short = $dom->createElement('span', '[podcast episode="' 
			. $div->getAttribute('data-episode-id')
			. '"]');
		print "removed " . $div->getAttribute('data-episode-id') . "\n";
		$div->parentNode->replaceChild($short, $div);
	}
	$images = $dom->getElementsByTagName('img');
	foreach ($images as $image) {
		$src = $image->getAttribute('src');
		if (   preg_match('/^http/', $src)
			|| preg_match('/^data:image/', $src)
		) { continue; }
		$matches = Array();

		$clean = sanititize_file_in_url($src);
		if ($src !== $clean) {
			$image->setAttribute('src', $clean);
			print $src . ' is now ' . $clean . "\n\r";
		}
	}
	$html = $dom->saveHTML();	
	return preg_replace( '/\[\[nid:\d+]]/', '', $html );
}

function sanititize_file_in_url( $filepath ) {
	if ( preg_match('/^(.*)(\/.*\/)(.+)$/', $filepath, $matches) ) {
		$file = sanitize_file_name(urldecode($matches[3])); //ha matches 0 is the whole thing of course
    $dir_array = explode('/', $matches[2]);
    $dir = '';
    foreach ($dir_array as $dir_frag) {
      if (!$dir) { $dir .= '/'; }
      if($dir_frag) {
        $dir .= urlencode(sanitize_file_name(urldecode($dir_frag))) . '/';
      }
    }
		$filepath = $matches[1] . $dir . urlencode($file);
	} else {
		$filepath = sanitize_file_name($filepath);
	}
	return $filepath;
}

function wp_check_filetype( $filename, $mimes = null ) {                         
    if ( empty($mimes) )                                                         
        $mimes = get_allowed_mime_types();                                       
    $type = false;                                                               
    $ext = false;                                                                
                                                                                 
    foreach ( $mimes as $ext_preg => $mime_match ) {                             
        $ext_preg = '!\.(' . $ext_preg . ')$!i';                                 
        if ( preg_match( $ext_preg, $filename, $ext_matches ) ) {                
            $type = $mime_match;                                                 
            $ext = $ext_matches[1];                                              
            break;                                                               
        }                                                                        
    }                                                                            
                                                                                 
    return compact( 'ext', 'type' );                                             
}                                                                                


function wp_get_mime_types() {                                                   
    return array(                                   
    // Image formats.                                                            
    'jpg|jpeg|jpe' => 'image/jpeg',                                              
    'gif' => 'image/gif',                                                        
    'png' => 'image/png',                                                        
    'bmp' => 'image/bmp',                                                        
    'tiff|tif' => 'image/tiff',                                                  
    'ico' => 'image/x-icon',                                                     
    // Video formats.                                                            
    'asf|asx' => 'video/x-ms-asf',                                               
    'wmv' => 'video/x-ms-wmv',                                                   
    'wmx' => 'video/x-ms-wmx',                                                   
    'wm' => 'video/x-ms-wm',                                                     
    'avi' => 'video/avi',                                                        
    'divx' => 'video/divx',                                                      
    'flv' => 'video/x-flv',                                                      
    'mov|qt' => 'video/quicktime',                                               
    'mpeg|mpg|mpe' => 'video/mpeg',                                              
    'mp4|m4v' => 'video/mp4',                                                    
    'ogv' => 'video/ogg',                                                        
    'webm' => 'video/webm',                                                      
    'mkv' => 'video/x-matroska',                                                 
    '3gp|3gpp' => 'video/3gpp', // Can also be audio                             
    '3g2|3gp2' => 'video/3gpp2', // Can also be audio                            
    // Text formats.                                                             
    'txt|asc|c|cc|h|srt' => 'text/plain',                                        
    'csv' => 'text/csv',                                                         
    'tsv' => 'text/tab-separated-values',                                        
    'ics' => 'text/calendar',                                                    
    'rtx' => 'text/richtext',                                                    
    'css' => 'text/css',                                                         
    'htm|html' => 'text/html',                                                   
    'vtt' => 'text/vtt',                                                         
    'dfxp' => 'application/ttaf+xml',                                            
    // Audio formats.                                                            
    'mp3|m4a|m4b' => 'audio/mpeg',                                               
    'ra|ram' => 'audio/x-realaudio',                                             
    'wav' => 'audio/wav',                                                        
    'ogg|oga' => 'audio/ogg',                                                    
    'mid|midi' => 'audio/midi',                                                  
    'wma' => 'audio/x-ms-wma',                                                   
    'wax' => 'audio/x-ms-wax',                                                   
    'mka' => 'audio/x-matroska',                                                 
    // Misc application formats.                                                 
    'rtf' => 'application/rtf',                                                  
    'js' => 'application/javascript',                                            
    'pdf' => 'application/pdf',                                                  
    'swf' => 'application/x-shockwave-flash',                                    
    'class' => 'application/java',                                               
    'tar' => 'application/x-tar',                                                
    'zip' => 'application/zip',                                                  
    'gz|gzip' => 'application/x-gzip',                                           
    'rar' => 'application/rar',                                                  
    '7z' => 'application/x-7z-compressed',                                       
    'exe' => 'application/x-msdownload',                                         
    'psd' => 'application/octet-stream',                                         
    'xcf' => 'application/octet-stream',                                         
    // MS Office formats.                                                        
    'doc' => 'application/msword',                                               
    'pot|pps|ppt' => 'application/vnd.ms-powerpoint',                            
    'wri' => 'application/vnd.ms-write',                                         
    'xla|xls|xlt|xlw' => 'application/vnd.ms-excel',                             
    'mdb' => 'application/vnd.ms-access',                                        
    'mpp' => 'application/vnd.ms-project',                                       
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'docm' => 'application/vnd.ms-word.document.macroEnabled.12',                
    'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    'dotm' => 'application/vnd.ms-word.template.macroEnabled.12',                
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',                  
    'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',           
    'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
    'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12',               
    'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',                  
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',      
    'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
    'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',         
    'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
    'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12',          
    'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',             
    'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
    'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12',             
    'onetoc|onetoc2|onetmp|onepkg' => 'application/onenote',                     
    'oxps' => 'application/oxps',                                                
    'xps' => 'application/vnd.ms-xpsdocument',                                   
    // OpenOffice formats.                                                       
    'odt' => 'application/vnd.oasis.opendocument.text',                          
    'odp' => 'application/vnd.oasis.opendocument.presentation',                  
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',                   
    'odg' => 'application/vnd.oasis.opendocument.graphics',                      
    'odc' => 'application/vnd.oasis.opendocument.chart',                         
    'odb' => 'application/vnd.oasis.opendocument.database',                      
    'odf' => 'application/vnd.oasis.opendocument.formula',                       
    // WordPerfect formats.                                                      
    'wp|wpd' => 'application/wordperfect',                                       
    // iWork formats.                                                            
    'key' => 'application/vnd.apple.keynote',                                    
    'numbers' => 'application/vnd.apple.numbers',                                
    'pages' => 'application/vnd.apple.pages',                                    
    );                                                                         
}                                                                                


function get_allowed_mime_types( $user = null ) {
	$t = wp_get_mime_types();

	unset( $t['swf'], $t['exe'] );                   
	if ( function_exists( 'current_user_can' ) )                                
		$unfiltered = $user ? user_can( $user, 'unfiltered_html' ) : current_user_can( 'unfiltered_html' ); 

	if ( empty( $unfiltered ) )                            
		unset( $t['htm|html'] );
	return $t;              
}           


//truncate term tables
$wp->beginTransaction();
$wp->exec('
TRUNCATE TABLE wp_terms;
TRUNCATE TABLE wp_term_taxonomy;
TRUNCATE pantheon_wp.wp_term_relationships;
SET GROUP_CONCAT_MAX_LEN = 1073741824;
');
$wp->commit();

$d6->beginTransaction();
$d6->exec('
SET GLOBAL max_allowed_packet=1024*1024*50;
SET @@session.group_concat_max_len = @@global.max_allowed_packet;
');
$d6->commit();


$term_insert_data = $d6->prepare('
SELECT *
FROM term_data
WHERE (vid = 9 OR vid = 2 OR vid = 1
    OR tid = 22221 OR tid = 23631 OR tid = 22491)
;'
);
$term_insert_data->execute();

$term_insert = $wp->prepare('
INSERT INTO wp_terms
(term_id, `name`, slug, term_group)
VALUES (
?, ?, REPLACE(LOWER(?), " ", "-"), ?
)
;'
);
$term_insert->bindParam(1, $tid);
$term_insert->bindParam(2, $name);
$term_insert->bindParam(3, $slug);
$term_insert->bindParam(4, $vid);

$wp->beginTransaction();
while ( $term = $term_insert_data->fetch(PDO::FETCH_ASSOC)) {
  if ($term['name'] === "Photo Essays") {
    $term['name'] = "Photoessays";
  } elseif ($term['name'] === "Crime and Justice") {
    $tid = $term['tid'];
    $name = $term['name'];
    $slug = 'crime-justice';
    $vid = $term['vid'];
    $term_insert->execute();
    continue;
  }
	$tid = $term['tid'];
	$name = $term['name'];
	$slug = $term['name'];
	$vid = $term['vid'];
	$term_insert->execute();
}
$wp->commit();


$taxonomy_data = $d6->prepare('
SELECT DISTINCT
d.tid `term_id`,
d.vid `taxonomy`
FROM mjd6.term_data d
INNER JOIN mjd6.term_node n
USING(tid)
WHERE (d.vid = 9 OR d.vid = 2 OR d.vid = 1 OR d.vid = 5
    OR d.tid = 22221 OR d.tid = 23631 OR d.tid = 22491)
;
'
);
$taxonomy_data->execute();

$tax_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_term_taxonomy
(term_id, taxonomy, description, parent)
VALUES (
?,
?,
"",
0
)
;'
);
$tax_insert->bindParam(1, $tid);
$tax_insert->bindParam(2, $tax);

$term_to_tax_term = [];
$wp->beginTransaction();
while ( $row = $taxonomy_data->fetch(PDO::FETCH_ASSOC)) {
	$tid = $row['term_id'];
	$tax = $row['taxonomy'];
	switch ($tax) {
	case "9":
		if ($tid === "16720" || $tid === "16734") { //is crime & justice or food
			$tax = "category";
			break;
		}
		$tax = "post_tag";
		break;
	case "2":
		$tax = "blog";
		if ($tid === "14") { //is  kdrum
			$tax = "category";
		}
		break;
	case "61": //media type
		if ($tid === "22221") { //is photoessay
			$tax = "post_tag";
			break;
		}
		continue 2;
	case "1":
		$tax = "category";
		break;
	case "5": //secondary tag
		if ($tid === "23631" || $tid === "22491") { //bite or inquiring minds
			$tax = "post_tag";
			break;
		}
		$tax = "mj_secondary_tags";
		break;
		continue 2;
	default:
		continue 2;
	}
	$tax_insert->execute();
  $term_to_tax_term[$row['term_id']] = $wp->lastInsertId();
}
$wp->commit();

// assign tags to articles
$term_rel_data = $d6->prepare("
SELECT DISTINCT n.nid, n.tid FROM mjd6.term_node n JOIN mjd6.term_data d
ON n.tid = d.tid
WHERE (d.vid = 9 OR d.vid = 2 OR d.vid = 1
    OR d.tid = 22221 OR d.tid = 23631 OR d.tid = 22491)
;
");
$term_rel_data->execute();

$term_rel_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_term_relationships
(object_id, term_taxonomy_id)
VALUES (?, ?)
');

$wp->beginTransaction();
while ( $term = $term_rel_data->fetch(PDO::FETCH_NUM)) {
  $term[1] = $term_to_tax_term[$term[1]];
	$term_rel_insert->execute($term);
}
$wp->commit();


echo "taxonomy done";


$wp->beginTransaction();
$wp->exec('
TRUNCATE pantheon_wp.wp_posts;
TRUNCATE pantheon_wp.wp_postmeta;
');
$wp->commit();

$post_data = $d6->prepare("
SELECT DISTINCT
n.nid,
n.uid,
FROM_UNIXTIME(p.published_at),
CONVERT_TZ(FROM_UNIXTIME(p.published_at), 'America/New_York','UTC'),
r.body,
n.title,
d.field_dek_value,
IF(
	LOCATE('/', a.dst),
	SUBSTR(a.dst,
		CHAR_LENGTH(a.dst) - LOCATE('/', REVERSE(a.dst)) + 2
	),
	a.dst
),
'',
'',
FROM_UNIXTIME(n.changed),
CONVERT_TZ(FROM_UNIXTIME(n.changed), 'America/New_York','UTC'),
'',
n.type,
IF(n.status = 1, 'publish', 'draft'),
0
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT('node/', n.nid)
LEFT JOIN mjd6.publication_date p
ON n.nid = p.nid
INNER JOIN mjd6.content_field_dek d
ON n.nid = d.nid
WHERE n.type IN ('article', 'blogpost', 'full_width_article')
;
");
$post_data->execute();


$post_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, `post_parent`)
VALUES (?, ?, ?, ?, ?, ?, ?,
?, ?, ?, ?, ?,
?, ?, ?, ?)
');

$wp->beginTransaction();
while ( $post = $post_data->fetch(PDO::FETCH_NUM)) {
	$post_insert->execute($post);
}
$wp->commit();


$about_data = $d6->prepare("
SELECT DISTINCT
n.nid,
n.uid,
FROM_UNIXTIME(p.published_at),
CONVERT_TZ(FROM_UNIXTIME(p.published_at), 'America/New_York','UTC'),
r.body,
n.title,
r.teaser,
'about'
,
'',
'',
FROM_UNIXTIME(n.changed),
CONVERT_TZ(FROM_UNIXTIME(n.changed), 'America/New_York','UTC'),
'',
n.type,
IF(n.status = 1, 'publish', 'draft'),
0
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT('node/', n.nid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
WHERE n.nid = 64
;
");
$about_data->execute();

$wp->beginTransaction();
while ( $post = $about_data->fetch(PDO::FETCH_NUM)) {
	$post_insert->execute($post);
}
$wp->commit();

$page_data = $d6->prepare("
SELECT DISTINCT
n.nid,
n.uid,
FROM_UNIXTIME(p.published_at),
CONVERT_TZ(FROM_UNIXTIME(p.published_at), 'America/New_York','UTC'),
r.body,
n.title,
r.teaser,
REPLACE(
	SUBSTR(a.dst,
	  CHAR_LENGTH('/about/')
	),
    '/',
    '-'
)
,
'',
'',
FROM_UNIXTIME(n.changed),
CONVERT_TZ(FROM_UNIXTIME(n.changed), 'America/New_York','UTC'),
'',
n.type,
IF(n.status = 1, 'publish', 'draft'),
64
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT('node/', n.nid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
WHERE n.type = 'page'
AND a.dst LIKE '%about%'
AND n.nid != 64
;
");
$page_data->execute();

$wp->beginTransaction();
while ( $post = $page_data->fetch(PDO::FETCH_NUM)) {
	$post_insert->execute($post);
}
$wp->commit();


$page_data = $d6->prepare("
SELECT DISTINCT
n.nid,
n.uid,
FROM_UNIXTIME(p.published_at),
CONVERT_TZ(FROM_UNIXTIME(p.published_at), 'America/New_York','UTC'),
r.body,
n.title,
r.teaser,
REPLACE(
  SUBSTR(a.dst,
    LOCATE('/', a.dst) + 1
  ),
  \"/\",
  \"-\"
)
,
'',
'',
FROM_UNIXTIME(n.changed),
CONVERT_TZ(FROM_UNIXTIME(n.changed), 'America/New_York','UTC'),
'',
n.type,
IF(n.status = 1, 'publish', 'draft'),
0
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT('node/', n.nid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
WHERE n.type = 'page'
AND a.dst NOT LIKE '%about%'
AND a.dst NOT LIKE 'toc%'
;
");
$page_data->execute();

$wp->beginTransaction();
while ( $post = $page_data->fetch(PDO::FETCH_NUM)) {
	$post_insert->execute($post);
}
$wp->commit();

// toc call
$page_data = $d6->prepare("
SELECT DISTINCT
r.nid,
r.uid,
FROM_UNIXTIME(p.published_at),
r.body,
r.title,
r.teaser,
a.dst,
FROM_UNIXTIME(n.changed),
n.type,
IF(n.status = 1, 'publish', 'draft')
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT('node/', n.nid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
WHERE (n.type = 'page' OR n.type = 'toc')
AND a.dst LIKE 'toc%'
;
");
$page_data->execute();

$toc_pages = Array();
$toc_year_pages = Array();
$toc_month_pages = Array();
$toc_magazine_pages = Array();

$redirects_needed = Array();

while ( $page = $page_data->fetch(PDO::FETCH_ASSOC)) {
  // form is toc/YYYY/MM/slug
  $path = preg_split('/\//', $page['dst']);
  $page['url_split'] = $path;
  $toc_year_pages[$path[1]] = True;
  if ( !array_key_exists(3, $path) ) {
    $toc_magazine_pages[$path[1] . $path[2]] = $page;
  } else {
    $toc_sub_pages []= $page;
  }
}

//same as post insert  but no id supplied
$page_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, `post_parent`)
VALUES (?,
FROM_UNIXTIME(?),
CONVERT_TZ(FROM_UNIXTIME(?), "America/New_York","UTC"),
 ?, ?, ?,
?, ?, ?,
FROM_UNIXTIME(?),
CONVERT_TZ(FROM_UNIXTIME(?), "America/New_York","UTC"),
?, ?, ?, ?)
');

//insert toc parent page
$wp->beginTransaction();
$page_insert->execute(Array(
  '', #post author
  "1970-1-1 00:00:00", //posted
  "1970-1-1 00:00:00", //posted in gmt
  '', //body
  'Magazine', //title
  '', //post_excerpt
  'mag', //slug
  '', //to ping
  '', //pinged
  "1970-1-1 00:00:00", //changed
  "1970-1-1 00:00:00", //changed in gmt
  '', //post content filtered
  'page', //type
  'publish', //pub status
  0 //parent
));
$toc_parent_id = $wp->lastInsertId();
$wp->commit();
$redirects_needed []= '/mag/';

//insert year parents
$wp->beginTransaction();
foreach ($toc_year_pages as $year => $boolean) {
  $page_insert->execute(Array(
    '', #post author
    "1970-1-1 00:00:00", //posted
    "1970-1-1 00:00:00", //posted in gmt
    '', //body
    $year, //title
    '', //post_excerpt
    $year, //slug
    '', //to ping
    '', //pinged
    "1970-1-1 00:00:00", //changed
    "1970-1-1 00:00:00", //changed in gmt
    '', //post content filtered
    'page', //type
    'publish', //pub status
    $toc_parent_id //parent
  ));
  $toc_year_pages[$year] = $wp->lastInsertId();
  $redirects_needed []= '/mag/' . $year;
}
$wp->commit();

$months_to_create = Array();
//find months to create
foreach ($toc_sub_pages as $page) {
  $date = $page['url_split'][1] . $page['url_split'][2];
  $months_to_create[$date] = Array($page['url_split'][1],$page['url_split'][2]);
}
foreach ($toc_magazine_pages as $page) {
  $date = $page['url_split'][1] . $page['url_split'][2];
  $months_to_create[$date] = Array($page['url_split'][1], $page['url_split'][2]);
}
$wp->beginTransaction();
foreach ($months_to_create as $date) {
  $year = $date[0];
  $month = $date[1];
  $redirects_needed []= '/mag/' . $year . '/' . $month;

  $page_insert->execute(Array(
    '', #post author
    "1970-1-1 00:00:00", //posted
    "1970-1-1 00:00:00", //posted in gmt
    '', //body
    $month,
    '', //post_excerpt
    $month,
    '', //to ping
    '', //pinged
    "1970-1-1 00:00:00", //changed
    "1970-1-1 00:00:00", //changed in gmt
    '', //post content filtered
    'page', //type
    'publish', //pub status
    $toc_year_pages[$year] //parent
  ));
  $toc_month_pages[$year . $month] = $wp->lastInsertId();
}
$wp->commit();

//insert magazine pages
$wp->beginTransaction();
foreach ($toc_magazine_pages as $date => $page) {
  // form is toc/YYYY/MM/slug
  $page_insert->execute(Array(
    $page['uid'], #post author
    $page['FROM_UNIXTIME(p.published_at)'], //posted
    'CONVERT_TZ(' . $page['FROM_UNIXTIME(p.published_at)'] . ', "America/New_York","UTC")', //posted
    $page['body'], //body
    $page['title'], //title
    $page['teaser'], //post_excerpt
    'toc', //slug
    '', //to ping
    '', //pinged
    $page['FROM_UNIXTIME(n.changed)'], //posted
    'CONVERT_TZ(' . $page['FROM_UNIXTIME(n.changed)'] . ', "America/New_York","UTC")', //posted
    '', //post content filtered
    'page', //type
    $page["IF(n.status = 1, 'publish', 'draft')"], //pub status
    $toc_month_pages[$date] //parent
  ));
}
$wp->commit();


$wp->beginTransaction();
foreach ($toc_sub_pages as $page) {
  // form is toc/YYYY/MM/slug
  $date = $page['url_split'][1] . $page['url_split'][2];
  $page_insert->execute(Array(
    $page['uid'], #post author
    $page['FROM_UNIXTIME(p.published_at)'], //posted
    'CONVERT_TZ(' . $page['FROM_UNIXTIME(p.published_at)'] . ', "America/New_York","UTC")', //posted
    $page['body'], //body
    $page['title'], //title
    $page['teaser'], //post_excerpt
    $page['url_split'][3], //slug
    '', //to ping
    '', //pinged
    $page['FROM_UNIXTIME(n.changed)'], //posted
    'CONVERT_TZ(' . $page['FROM_UNIXTIME(n.changed)'] . ', "America/New_York","UTC")', //posted
    '', //post content filtered
    'page', //type
    $page["IF(n.status = 1, 'publish', 'draft')"], //pub status
    $toc_month_pages[$page['url_split'][1] . $page['url_split'][2]] //parent
  ));
}
$wp->commit();

$redirect_item_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
1,
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "America/New_York","UTC"),
"",
"",
:post_title, #from not hashed
:post_excerpt, # to url
:post_name, #from hashed md5
"",
"",
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "America/New_York","UTC"),
"",
"vip-legacy-redirect",
"publish",
:post_parent, # to as post id
""
)
;');

$wp->beginTransaction();
foreach ($redirects_needed as $dst) {
  $dst = '/' . $dst;
  $redirect_item_insert->execute(Array(
		"post_name" => md5($dst),
		"post_title" => $dst,
		"post_parent" => null,
		"post_excerpt" => '/',
  ));
}
$wp->commit();


$article_term_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_terms
(name, slug, term_group)
VALUES (
?,
?,
0
)
;'
);

$article_types = Array('article', 'blogpost', 'full_width_article');
$article_type_terms = Array();
$wp->beginTransaction();
foreach ( $article_types as $type) {
  $article_term_insert->execute(Array($type, $type));
  $article_type_terms[$type] = $wp->lastInsertId();
}
$wp->commit();

$tax_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_term_taxonomy
(term_id, taxonomy, description, parent)
VALUES (
?,
"mj_content_type",
"",
0
)
;'
);
$wp_tax_id = Array();
$wp->beginTransaction();
foreach ($article_type_terms as $type => $term_id) {
  $tax_insert->execute(Array($term_id));
  $wp_tax_id[$type] = $wp->lastInsertId();
}
$wp->commit();


$wp->beginTransaction();
$wp->exec('
INSERT IGNORE INTO pantheon_wp.wp_term_relationships
(object_id, term_taxonomy_id)
SELECT p.ID, tax.term_taxonomy_id
FROM wp_posts p
JOIN
wp_terms term
ON (p.post_type = term.slug)
JOIN
wp_term_taxonomy tax
ON (tax.term_id = term.term_id)
;'
);
$wp->commit();

// Update tag counts.
$wp->beginTransaction();
$wp->exec('
UPDATE pantheon_wp.wp_term_taxonomy tt
SET `count` = (
SELECT COUNT(tr.object_id)
FROM pantheon_wp.wp_term_relationships tr
WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
)
;
');
$wp->commit();

$wp->beginTransaction();
$wp->exec('
UPDATE pantheon_wp.wp_posts
  SET post_type="post"
  WHERE post_type="article";

UPDATE pantheon_wp.wp_posts
  SET post_type="post"
  WHERE post_type="full_width_article";

UPDATE pantheon_wp.wp_posts
  SET post_type="post"
  WHERE post_type="blogpost";

');
$wp->commit();


//for blog body
$post_content = $d6->prepare("
SELECT DISTINCT
n.nid,
IF(
  e.field_extended_body_value IS NULL,
  b.field_short_body_value,
  CONCAT(b.field_short_body_value, e.field_extended_body_value)
)
FROM mjd6.node n
INNER JOIN mjd6.content_field_short_body b
USING(vid)
INNER JOIN mjd6.content_type_blogpost e
USING(vid)
WHERE n.type='blogpost'
;
");
$post_content->execute();

$content_insert = $wp->prepare('
UPDATE wp_posts
SET post_content=?
WHERE ID=?
;
');

$wp->beginTransaction();
while ( $content = $post_content->fetch(PDO::FETCH_NUM)) {
	$content_insert->execute(array(
		fix_post_body($content[1]),
		$content[0],
	));
}
$wp->commit();


//for article bodys
$post_content = $d6->prepare('
SELECT DISTINCT
n.nid,
IF(
  text.text IS NULL,
  b.field_short_body_value,
  CONCAT(b.field_short_body_value,
    text.text
  )
)
FROM mjd6.node n
INNER JOIN mjd6.content_field_short_body b
USING(vid)
JOIN
(
  SELECT vid, GROUP_CONCAT(field_article_text_value SEPARATOR "</p>") AS text
  FROM mjd6.content_field_article_text
  GROUP BY vid
) AS text
USING(vid)
WHERE n.type="article"
;
');
$post_content->execute();

$wp->beginTransaction();
while ( $content = $post_content->fetch(PDO::FETCH_NUM)) {
	$content_insert->execute(array(
		fix_post_body($content[1]),
		$content[0],
	));
}
$wp->commit();


//for full width bodys
$post_content = $d6->prepare('
SELECT DISTINCT
n.nid, b.field_short_body_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_short_body b
USING(vid)
WHERE n.type="full_width_article"
;
');
$post_content->execute();

$wp->beginTransaction();
while ( $content = $post_content->fetch(PDO::FETCH_NUM)) {
	$content_insert->execute(array(
		fix_post_body($content[1]),
		$content[0],
	));
}
$wp->commit();


$meta_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_postmeta
(post_id, meta_key, meta_value)
VALUES (?, ?, ?)
;
');

//for dek
$meta_data = $d6->prepare('
SELECT DISTINCT
n.nid, "mj_dek", d.field_dek_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_dek d
USING(vid)
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_NUM)) {
  if ($meta[1]) {
    $meta_insert->execute($meta);
  }
}
$wp->commit();

//for dateline override
$meta_data = $d6->prepare('
SELECT DISTINCT n.nid, "mj_issue_date", d.field_issue_date_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_issue_date d
USING(vid)
WHERE d.field_issue_date_value IS NOT NULL
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_NUM)) {
  if ($meta[1]) {
    $meta_insert->execute($meta);
  }
}
$wp->commit();

//for byline override
$meta_data = $d6->prepare('
SELECT DISTINCT n.nid, "mj_byline_override", b.field_byline_override_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_byline_override b
USING(vid)
WHERE b.field_byline_override_value IS NOT NULL
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_NUM)) {
  if ($meta[1]) {
    $meta_insert->execute($meta);
  }
}
$wp->commit();

//for social
$meta_data = $d6->prepare('
SELECT DISTINCT
n.nid,
t.field_social_title_value,
d.field_social_dek_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_social_dek d
USING(vid)
INNER JOIN mjd6.content_field_social_title t
USING(vid)
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_ASSOC)) {
  if ($meta['field_social_title_value']) {
    $meta_insert->execute( array(
      $meta['nid'],
      'mj_social_hed',
      $meta['field_social_title_value']
    ) );
  }
  if ($meta['field_social_dek_value']) {
    $meta_insert->execute( array(
      $meta['nid'],
      'mj_social_dek',
      $meta['field_social_dek_value']
    ) );
  }
  $meta_insert->execute( array($meta['nid'], 'mj_fb_instant_exclude', 'true') );
}
$wp->commit();

//for alt
$meta_data = $d6->prepare('
SELECT DISTINCT
n.nid,
t.field_alternate_title_value,
d.field_alternate_dek_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_alternate_dek d
USING(vid)
INNER JOIN mjd6.content_field_alternate_title t
USING(vid)
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_ASSOC)) {
  if ( $meta['field_alternate_title_value'] ) {
    $meta_insert->execute( array(
      $meta['nid'],
      'mj_promo_hed',
      $meta['field_alternate_title_value']
    ) );
  }
  if ( $meta['field_alternate_dek_value'] ) {
    $meta_insert->execute( array(
      $meta['nid'],
      'mj_promo_dek',
      $meta['field_alternate_dek_value']
    ) );
  }
}
$wp->commit();

//for css
$meta_data = $d6->prepare('
SELECT DISTINCT
n.nid,
c.field_css_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_css c
USING(vid)
;
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_ASSOC)) {
  if ( $css = $meta['field_css_value'] ) {
	  $meta_insert->execute(array(
		  $meta['nid'],
		  'mj_custom_css',
		  $css,
	  ) );
  }
}
$wp->commit();

//for relateds
$meta_data = $d6->prepare('
SELECT DISTINCT n.nid,
GROUP_CONCAT(
  DISTINCT r.field_related_articles_nid
  SEPARATOR ","
) `relateds`
FROM mjd6.node n
INNER JOIN mjd6.content_field_related_articles r
USING(vid)
GROUP BY n.nid
');
$meta_data->execute();

$wp->beginTransaction();
while ( $meta = $meta_data->fetch(PDO::FETCH_ASSOC)) {
  $related_value = serialize( explode(',', $meta['relateds']) );

  if ( $related_value ) {
    $meta_insert->execute(array($meta['nid'], 'mj_related_articles', $related_value) );
  }
}
$wp->commit();

echo "posts done";

/*begin author migration */
$wp->beginTransaction();
$wp->exec('
DELETE FROM pantheon_wp.wp_users WHERE ID > 1;
DELETE FROM pantheon_wp.wp_usermeta WHERE user_id > 1;
');
$wp->commit();




$author_data = $d6->prepare("
SELECT DISTINCT
n.title,
u.uid,
u.mail,
a.field_user_uid,
a.field_twitter_user_value,
a.field_last_name_value,
a.field_author_bio_short_value,
a.field_author_title_value,
a.field_author_bio_value,
a.field_photo_fid,
a.field_author_title_value
FROM mjd6.content_field_byline b
INNER JOIN mjd6.node n
ON (n.nid = b.field_byline_nid)
INNER JOIN mjd6.content_type_author a
ON (a.vid = n.vid)
LEFT JOIN mjd6.users u
ON (u.uid=a.field_user_uid)
WHERE n.title IS NOT NULL
;
");
$author_data->execute();


$author_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_users
(user_nicename, user_login, user_registered, display_name, user_email)
VALUES (
  REPLACE(LOWER(?), " ", "-"), # NICENAME lowercase, - instead of space
  REPLACE(LOWER(?), " ", ""), # login lowercase, no spaces
  FROM_UNIXTIME("1970-1-1 00:00:00"),
  ?, # Display name
  ?  # email
)
');


$uid_to_author_meta = Array();
$author_name_to_author_meta = Array();
$wp->beginTransaction();
while ( $author = $author_data->fetch(PDO::FETCH_ASSOC)) {
  $author_insert->execute(Array(
    $author['title'],
    $author['title'],
    $author['title'],
    $author['mail']
  ));
  $uid_to_author_meta[$wp->lastInsertId()] = $author;
  $author['wp_id'] = $wp->lastInsertId();
  $author_name_to_author_meta[$author['title']] = $author;
}
$wp->commit();

$roles_data = $d6->prepare("
SELECT DISTINCT
u.name,
u.mail
FROM mjd6.users u
INNER JOIN mjd6.users_roles r
USING (uid)
;
");
$roles_data->execute();

$wp->beginTransaction();
while ( $author = $roles_data->fetch(PDO::FETCH_ASSOC)) {
  if ( array_key_exists( $author['name'], $author_name_to_author_meta ) ) {
    continue;
  }
  $author_insert->execute(Array(
    $author['name'],
    $author['name'],
    $author['name'],
    $author['mail']
  ));
  $author['wp_id'] = $wp->lastInsertId();
  $author_name_to_author_meta[$author['name']] = $author;
  $uid_to_author_meta[$wp->lastInsertId()] = $author;

}
$wp->commit();

$author_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_usermeta (user_id, meta_key, meta_value)
VALUES ( ?, ?, ? )
;
");

$author_meta_insert->bindParam(1, $uiid);
$author_meta_insert->bindParam(2, $key);
$author_meta_insert->bindParam(3, $value);
$wp->beginTransaction();
foreach ( $uid_to_author_meta as $uid => $author ) {
  $uiid = $uid;

  if (array_key_exists('title', $author)) {
	  $key = "nickname";
	  $value = $author['title'] . ' the Gozerian';
	  $author_meta_insert->execute();
  }

  if (array_key_exists('field_twitter_user_value', $author)) {
    $key = "mj_user_twitter";
    $value = $author['field_twitter_user_value'];
    $author_meta_insert->execute();
  }

  if (array_key_exists('field_author_bio_short_value', $author)) {
    $key = "description";
    $value = $author['field_author_bio_short_value'];
    $author_meta_insert->execute();
  }

  if (array_key_exists('field_author_bio_value', $author)) {
    $key = "mj_user_full_bio";
    $value = $author['field_author_bio_value'];
    $author_meta_insert->execute();
  }

  if (array_key_exists('field_author_title_value', $author)) {
    $key = "mj_user_position";
    $value = $author['field_author_title_value'];
    $author_meta_insert->execute();
  }

  //everybody is a former author! Later we can make active users active
  $key = 'wp_capabilities';
  $value = 'a:1:{s:13:"former_author";s:1:"1";}';
  $author_meta_insert->execute();

  $key = 'wp_user_level';
  $value = 1;
  $author_meta_insert->execute();

  $key = 'rich_editing';
  $value = 'true';
  $author_meta_insert->execute();
}
$wp->commit();


//Create byline tags

//naming for co authors taxonomy is cap-username
$byline_titles_data = $d6->prepare("
SELECT DISTINCT
n.title
FROM mjd6.content_field_byline b
INNER JOIN mjd6.node n
ON (n.nid = b.field_byline_nid)
;"
);
$byline_titles_data->execute();

$byline_titles_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_terms
(name, slug, term_group)
VALUES (
?,
CONCAT( "cap-", REPLACE(LOWER(?), " ", "-") ),
0
)
;'
);

$term_id_to_name = array();
$wp->beginTransaction();
while ( $byline = $byline_titles_data->fetch(PDO::FETCH_ASSOC)) {
  $byline_titles_insert->execute(Array(
    $byline['title'],
    $byline['title']
  ));
  $term_id_to_name[$wp->lastInsertId()] = $byline['title'];
}
$wp->commit();

$byline_taxonomy_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_term_taxonomy
(term_id, taxonomy, description)
VALUES (
?,
'author',
?)
;
");

$name_to_tax_id = array();
$wp->beginTransaction();
foreach ( $term_id_to_name as $term_id => $name ) {
  $author_meta = $author_name_to_author_meta[$name];
  $description = $name
    . ' ' . $author_meta['field_last_name_value']
    . ' ' . $name
    . ' ' . $author_meta['wp_id']
    . ' ' . $author_meta['mail']
  ;
  $byline_taxonomy_insert->execute(Array(
    $term_id,
    $description
  ));
  $name_to_tax_id[$name] = $wp->lastInsertId();
}
$wp->commit();

$byline_term_data = $d6->prepare("
SELECT DISTINCT
n.nid,
b.nid `node`,
n.title `title`
FROM mjd6.content_field_byline b
INNER JOIN mjd6.node n
ON (n.nid = b.field_byline_nid)
INNER JOIN mjd6.node a
ON (b.vid = a.vid)
;"
);
$byline_term_data->execute();

$byline_term_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_term_relationships
(object_id, term_taxonomy_id)
VALUES (?, ?)
;
");

$wp->beginTransaction();
while ( $term = $byline_term_data->fetch(PDO::FETCH_ASSOC)) {
  if (array_key_exists($term['title'], $author_name_to_author_meta)) {
    $byline_term_insert->execute(Array(
      $term['node'],
      $name_to_tax_id[$term['title']]
    ));
  }
}
$wp->commit();

$byline_count_update = $wp->prepare("
UPDATE wp_term_taxonomy SET count
= (SELECT count(*) from wp_term_relationships where term_taxonomy_id=?)
WHERE term_taxonomy_id=?
;
");
$wp->beginTransaction();

foreach ($name_to_tax_id as $name => $tax_id) {
    $byline_count_update->execute(Array($tax_id, $tax_id));
}
$wp->commit();


// end create byline taxonomy terms



//author roles who are active users
$roles_data = $d6->prepare("
SELECT DISTINCT
u.name
FROM mjd6.users u
INNER JOIN mjd6.users_roles r
USING (uid)
WHERE u.mail IS NOT NULL
;
");
$roles_data->execute();

$roles_insert = $wp->prepare("
UPDATE pantheon_wp.wp_usermeta
SET meta_value = ?
WHERE meta_key = ?
AND user_id = ?
;
");
$wp->beginTransaction();
while ( $role = $roles_data->fetch(PDO::FETCH_ASSOC)) {
  $user_id = $author_name_to_author_meta[$role['name']]['wp_id'];
  $roles_insert->execute(Array(
    'a:1:{s:6:"author";s:1:"1";}',
    'wp_capabilities',
    $user_id
  ));
  $roles_insert->execute(Array(
    2,
    'wp_user_level',
    $user_id
  ));
}
$wp->commit();

$wp->beginTransaction();
$wp->exec("
UPDATE pantheon_wp.wp_usermeta
SET meta_value = 'a:1:{s:13:\"administrator\";s:1:\"1\";}'
WHERE user_id IN (1) AND meta_key = 'wp_capabilities'
;
UPDATE pantheon_wp.wp_usermeta
SET meta_value = '10'
WHERE user_id IN (1) AND meta_key = 'wp_user_level'
;

UPDATE pantheon_wp.wp_posts
SET post_author = NULL
WHERE post_author NOT IN (SELECT DISTINCT ID FROM pantheon_wp.wp_users)
;
");
$wp->commit();

//author photo
$author_image_data = $d6->prepare("
SELECT DISTINCT
n.nid,
a.field_user_uid,
f.filemime,
f.filepath,
f.filename,
n.title,
a.field_photo_fid
FROM mjd6.node n
INNER JOIN mjd6.content_type_author a
USING(vid)
INNER JOIN mjd6.files f
ON(a.field_photo_fid = f.fid)
;
");
$author_image_data->execute();

$author_image_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt, guid,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
:post_author,
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "America/New_York","UTC"),
"",
:post_title,
"",
:post_name,
"",
"",
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "America/New_York","UTC"),
:guid,
"",
"attachment",
"inherit",
NULL,
:post_mime_type
)
;
');


$wp->beginTransaction();
while ( $image = $author_image_data->fetch(PDO::FETCH_ASSOC)) {
  $uid  = $author_name_to_author_meta[$image['title']]['wp_id'];
  $guid = $FILEDIR_ABS . preg_replace('/files\//', '', $image['filepath']);
  $post_name = preg_replace("/\.[^.]+$/", "", $image['filename'] );
  $author_image_insert->execute(array(
    ':post_author' => $uid,
    ':post_title' => sanititize_file_in_url($post_name),
    ':post_name' => sanititize_file_in_url($post_name),
    ':guid' => sanititize_file_in_url($guid),
    ':post_mime_type' => $image['filemime'],
  ));
  $author_name_to_author_meta[$image['title']]['image_location'] =
     preg_replace('/files\//', $FILEDIR, $image['filepath']);
  $author_name_to_author_meta[$image['title']]['image_id'] = $wp->lastInsertId();
}
$wp->commit();

$author_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_usermeta (user_id, meta_key, meta_value)
VALUES ( ?, ?, ? )
;
");

$wp->beginTransaction();
foreach ( $author_name_to_author_meta as $author ) {
  if ( array_key_exists('image_id', $author)
    && array_key_exists('wp_id', $author)
  ) {
    $author_meta_insert->execute(array(
      $author['wp_id'],
      "mj_author_image_id",
      $author['image_id']
    ));
  }
}
$wp->commit();

$author_image_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_postmeta (post_id, meta_key, meta_value)
VALUES ( ?, ?, ? )
;
");
$wp->beginTransaction();
foreach ( $author_name_to_author_meta as $author ) {
  if ( array_key_exists('image_id', $author)
    && array_key_exists('wp_id', $author)
  ) {
    $author_image_meta_insert->execute(array(
      $author['image_id'],
      '_wp_attached_file',
      $author['image_location']
    ) );
  }
}
$wp->commit();



echo "authors done";


/* end author data */


//for master images
$master_data = $d6->prepare('
SELECT DISTINCT
n.nid,
n.uid,
p.published_at,
n.changed,
n.status,
i.field_master_image_data,
c.field_master_image_caption_value,
b.field_art_byline_value,
s.field_suppress_master_image_value,
f.filemime,
f.filepath,
f.filename
FROM mjd6.node n
LEFT JOIN mjd6.content_field_master_image i
USING(vid)
LEFT JOIN mjd6.content_field_master_image_caption c
USING(vid)
LEFT JOIN mjd6.content_field_art_byline b
USING(vid)
LEFT JOIN mjd6.content_field_suppress_master_image s
USING(vid)
LEFT JOIN mjd6.files f
ON(i.field_master_image_fid = f.fid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
;
');
$master_data->execute();

$master_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt, guid,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
:post_author,
FROM_UNIXTIME(:post_date), #post date
CONVERT_TZ(FROM_UNIXTIME(:post_date), "America/New_York","UTC"),
"", #post content (description)
:post_title,
:post_excerpt,
:post_name,
"",
"",
FROM_UNIXTIME(:post_modified),
CONVERT_TZ(FROM_UNIXTIME(:post_modified), "America/New_York","UTC"),
:guid,
"",
"attachment",
"inherit",
:post_parent,
:post_mime_type
)
;
');

$master_meta_rows = array();

$wp->beginTransaction();
while ( $master = $master_data->fetch(PDO::FETCH_ASSOC)) {
  if (!$master['field_master_image_data']) { continue; }

  $master_data_array = unserialize($master['field_master_image_data']);

  $guid = preg_replace('/files\//', $FILEDIR_ABS, $master['filepath']);
  $post_name = preg_replace("/\.[^.]+$/", "", $master['filename'] );
  $post_title = $master_data_array['title']
    ? $master_data_array['title']
    : $post_name
  ;


  $master_insert->execute(array(
    ':post_author' => $master['uid'],
    ':post_date' => $master['published_at'],
    ':post_title' => sanititize_file_in_url($post_title),
    ':post_name' => sanititize_file_in_url($post_name),
    ':post_modified' => $master['changed'],
    ':guid' => sanititize_file_in_url($guid),
    ':status' => $master['status'],
    ':post_parent' => $master['nid'],
    ':post_mime_type' => $master['filemime'],
    ':post_excerpt' => $master['field_master_image_caption_value'],

  ) );


  $master_meta_rows[] = array(
    'nid' => $master['nid'],
    'image_id' => $wp->lastInsertId(),
    'filepath' => preg_replace('/files\//', $FILEDIR, $master['filepath']),
    'master_image' => $wp->lastInsertId(),
    'master_image_byline' => $master['field_art_byline_value'],
    'master_image_suppress' => $master['field_suppress_master_image_value'],
  );

}
$wp->commit();



$master_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_postmeta
(post_id, meta_key, meta_value)
VALUES (?, ?, ?)
;
");
$wp->beginTransaction();
foreach ( $master_meta_rows as $row ) {
  if ( $row['master_image_suppress'] ) {
    $master_meta_insert->execute(array(
      $row['nid'],
      'featured-image-display',
      'false',
    ) );
  }

  if ( $row['image_id'] ) {
    $master_meta_insert->execute(array(
      $row['nid'],
      '_thumbnail_id',
      $row['image_id']
    ) );
  }

  if ( $row['filepath'] ) {
    $master_meta_insert->execute(array(
      $row['image_id'],
      '_wp_attached_file',
      $row['filepath']
    ) );
  }

  if ( $row['master_image_byline'] ) {
    $master_meta_insert->execute(array(
      $row['image_id'],
      '_media_credit',
      $row['master_image_byline']
    ) );
  }

}
$wp->commit();

//TITLE IMAGES HERE
$title_data = $d6->prepare('
SELECT DISTINCT
n.nid,
n.uid,
p.published_at,
n.changed,
n.status,
i.field_title_image_data,
i.field_title_image_credit_value,
f.filemime,
f.filepath,
f.filename
FROM mjd6.node n
INNER JOIN mjd6.content_type_full_width_article i
USING(vid)
INNER JOIN mjd6.files f
ON(i.field_title_image_fid = f.fid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
;
');
$title_data->execute();

$title_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt, guid,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
:post_author,
FROM_UNIXTIME(:post_date),
CONVERT_TZ(FROM_UNIXTIME(:post_date), "America/New_York","UTC"),
"",
:post_title,
"",
:post_name,
"",
"",
FROM_UNIXTIME(:post_modified),
CONVERT_TZ(FROM_UNIXTIME(:post_modified), "America/New_York","UTC"),
:guid,
"",
"attachment",
"inherit",
:post_parent,
:post_mime_type
)
;
');

$title_meta_rows = array();

$wp->beginTransaction();
while ( $title = $title_data->fetch(PDO::FETCH_ASSOC)) {
  if (!$title['field_title_image_data']) { continue; }

  $title_data_array = unserialize($title['field_title_image_data']);

  $guid = preg_replace('/files\//', $FILEDIR_ABS, $title['filepath']);
  $post_name = preg_replace("/\.[^.]+$/", "", $title['filename'] );
  $post_title = $title_data_array['title']
    ? $title_data_array['title']
    : $post_name
  ;


  $title_insert->execute(array(
    ':post_author' => $title['uid'],
    ':post_date' => $title['published_at'],
    ':post_title' => sanititize_file_in_url($post_title),
    ':post_name' => sanititize_file_in_url($post_name),
    ':post_modified' => $title['changed'],
    ':guid' => sanititize_file_in_url($guid),
    ':status' => $title['status'],
    ':post_parent' => $title['nid'],
    ':post_mime_type' => $title['filemime'],
  ) );



  $title_meta_rows[] = array(
    'nid' => $title['nid'],
    'image_id' => $wp->lastInsertId(),
    'filepath' => preg_replace('/files\//', $FILEDIR, $title['filepath']),
    'title_image_credit' => $title['field_title_image_credit_value'],
  );
}
$wp->commit();


$title_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_postmeta
(post_id, meta_key, meta_value)
VALUES (?, ?, ?)
;
");
$wp->beginTransaction();
foreach ( $title_meta_rows as $row ) {

  $title_meta_insert->execute(array(
    $row['nid'],
    'post_mj_title_image_thumbnail_id',
    $row['image_id']
  ) );

  $title_meta_insert->execute(array(
    $row['image_id'],
    '_wp_attached_file',
    $row['filepath']
  ) );

  $master_meta_insert->execute(array(
    $row['image_id'],
    '_media_credit',
    $row['title_image_credit']
  ) );

}
$wp->commit();

echo "images done";

$file_data = $d6->prepare('
SELECT DISTINCT
f.uid,
u.nid,
p.published_at,
n.changed,
n.status,
f.filemime,
f.filename,
f.filepath,
f.fid
FROM mjd6.upload u
INNER JOIN mjd6.files f
USING(fid)
INNER JOIN mjd6.node n
ON(u.nid = n.nid)
JOIN mjd6.publication_date p
ON n.nid = p.nid
;
');
$file_data->execute();

$file_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt, guid,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
:post_author,
FROM_UNIXTIME(:post_date),
CONVERT_TZ(FROM_UNIXTIME(:post_date), "America/New_York","UTC"),
"",
:post_title,
"",
:post_name,
"",
"",
FROM_UNIXTIME(:post_modified),
CONVERT_TZ(FROM_UNIXTIME(:post_modified), "America/New_York","UTC"),
:guid,
"",
"attachment",
"inherit",
:post_parent,
:post_mime_type
)
;
');

$file_meta_rows = array();

$wp->beginTransaction();
while ( $file = $file_data->fetch(PDO::FETCH_ASSOC)) {

  $guid = preg_replace('/files\//', $FILEDIR_ABS, $file['filepath']);
  $post_name = preg_replace("/\.[^.]+$/", "", $file['filename'] );

  $file_insert->execute(array(
    ':post_author' => $file['uid'],
    ':post_date' => $file['published_at'],
    ':post_title' => sanititize_file_in_url($post_name),
    ':post_name' => sanititize_file_in_url($post_name),
    ':post_modified' => $file['changed'],
    ':guid' => sanititize_file_in_url($guid),
    ':status' => $file['status'],
    ':post_parent' => $file['nid'],
    ':post_mime_type' => $file['filemime'],
  ) );


  $file_meta_rows[] = array(
    'nid' => $file['nid'],
    'fid' => $wp->lastInsertId(),
    'filepath' => preg_replace('/files\//', $FILEDIR, $file['filepath']),
  );

}
$wp->commit();


$file_meta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_postmeta
(post_id, meta_key, meta_value)
VALUES (?, ?, ?)
;
");
$wp->beginTransaction();
foreach ( $file_meta_rows as $row ) {

  $file_meta_insert->execute(array(
    $row['fid'],
    '_wp_attached_file',
    $row['filepath']
  ) );

  $file_meta_insert->execute(array(
    $row['nid'],
    'file_attachment',
    $row['fid']
  ) );
}
$wp->commit();

echo "files done";

// do zoninator

$zones = Array(
  'top_stories' => Array(
    324446, 324431, 324441, 324436, 324531,
    324576, 324296, 324626, 324616, 324426, 324586
  ),
  'homepage_featured' => Array(324801),
  'homepage_photoessay' => Array(),
  'homepage_investigations' => Array(),
);
$zone_descriptions = Array(
  'top_stories' => Array('description' => 'Controls the top story, three side stories and six "more featured" stories on the homepage, as well as the top story widget on internal pages.'),
  'homepage_featured' => Array('description' => 'Controls the "Featured" section on the homepage. This should only contain 1 story.'),
  'homepage_photoessay' => Array('description' => 'Controls the "Exposure" section on the homepage. This should only contain 1 story.'),
  'homepage_investigations' => Array('description' => 'Controls the "Investigations" section on the homepage. This should only contain 4 stories.'),
);

foreach ($zones as $zone => $queue) {
  $zone_term_insert = $wp->prepare('
  INSERT IGNORE INTO wp_terms
  (name, slug)
  VALUES (?, ?)
  ;
  ');
  $wp->beginTransaction();
  $zone_term_insert->execute(array($zone, $zone));

  $zone_term_id = $wp->lastInsertId();
  $wp->commit();

  $zone_tax_insert = $wp->prepare('
  INSERT IGNORE INTO wp_term_taxonomy
  (term_id, taxonomy, description)
  VALUES (?, "zoninator_zones", ?)
  ;
  ');


  $description = $zone_descriptions[$zone];

  $wp->beginTransaction();
  $zone_tax_insert->execute(array(
    $zone_term_id,
    serialize($description)
  ));
  $zone_tax_id = $wp->lastInsertId();
  $wp->commit();



  $zone_meta_insert = $wp->prepare('
  INSERT IGNORE INTO wp_postmeta
  (post_id, meta_key, meta_value)
  VALUES (?, ?, ?)
  ;
  ');

  $meta_key = '_zoninator_order_' . $zone_term_id;

  $wp->beginTransaction();
  for ($i = 0; $i < count($queue); $i++) {
    $zone_meta_insert->execute(Array(
      $queue[$i],
      $meta_key,
      ($i + 1)
    ));
  }
  $wp->commit();

}
echo "zoninator filled";
?>
