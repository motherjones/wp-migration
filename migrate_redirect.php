<?php
$hostname="localhost";  
$username="root";   
$password=$argv[1];
$d6_db = "mjd6";  
$wp_db = "pantheon_wp";  


$d6 = new PDO("mysql:host=$hostname;dbname=$d6_db", $username, $password);  

$wp = new PDO("mysql:host=$hostname;dbname=$wp_db", $username, $password);  

//GET LEGACY REDIRECTS
$legacy_redirects = $d6->prepare('
SELECT
src,
dst
FROM mjd6.legacy_redirect
;'
);
$legacy_redirects->execute();

$redirect_post_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
VALUES (
1,
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "PST8PDT","UTC"),
"",
:post_title, #from not hashed
:post_excerpt, # to url
:post_name, #from hashed md5
"",
"",
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "PST8PDT","UTC"),
"",
"vip-legacy-redirect",
"publish",
:post_parent, # to as post id
""
)
;
');

$redirects = Array();

$wp->beginTransaction();
while ( $redirect = $legacy_redirects->fetch(PDO::FETCH_ASSOC)) {
	$redirect_post_insert->execute(Array(
		"post_name" => md5('/' . $redirect['src']),
		"post_title" => '/' . $redirect['src'],
		"post_parent" => null,
		"post_excerpt" => '/' . $redirect['dst'],
	));
}
$wp->commit();

//GET MANUAL REDIRECTS
$manual_redirects = $d6->prepare('
SELECT source, redirect FROM path_redirect 
WHERE redirect NOT LIKE "node%"
AND source != redirect
;'
);
$manual_redirects->execute();

$wp->beginTransaction();
while ( $redirect = $manual_redirects->fetch(PDO::FETCH_ASSOC)) {
	$redirect_post_insert->execute(Array(
		"post_name" => md5('/' . $redirect['source']),
		"post_title" => '/' . $redirect['source'],
		"post_parent" => null,
		"post_excerpt" => '/' . $redirect['redirect'],
	));
}
$wp->commit();

//UPDATE PAGES WITH SLASHES IN THEM

$page_redirects = $d6->prepare('
SELECT DISTINCT
a.dst,
REPLACE(
	a.dst,
    "/",
    "-"
)
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT("node/", n.nid)
WHERE n.type = "page"
AND a.dst NOT LIKE "%about%"
AND a.dst NOT LIKE "%toc%"
AND a.dst LIKE "%/%"
AND n.status = 1
;
');
$page_redirects->execute();
$wp->beginTransaction();
while ( $redirect = $page_redirects->fetch(PDO::FETCH_NUM)) {
	$redirect_post_insert->execute(Array(
		"post_name" => md5('/' . $redirect[0]),
		"post_title" => '/' . $redirect[0],
		"post_parent" => null,
		"post_excerpt" => '/' . $redirect[1],
	));
}
$wp->commit();

// UPDATE ABOUT PAGES W/ SUBDIRS
$page_redirects = $d6->prepare('
SELECT DISTINCT
a.dst,
CONCAT("/about/",
	REPLACE(
	  SUBSTR(a.dst, 
		LOCATE("/", a.dst) + 1
	  ), 
	  "/",
	  "-"
	)
)
FROM mjd6.node n
INNER JOIN mjd6.node_revisions r
USING(vid)
LEFT OUTER JOIN mjd6.url_alias a
ON a.src = CONCAT("node/", n.nid)
WHERE n.type = "page"
AND a.dst LIKE "about/%/%"
AND n.status = 1
;
');
$page_redirects->execute();
$wp->beginTransaction();
while ( $redirect = $page_redirects->fetch(PDO::FETCH_NUM)) {
	$redirect_post_insert->execute(Array(
		"post_name" => md5('/' . $redirect[0]),
		"post_title" => '/' . $redirect[0],
		"post_parent" => null,
		"post_excerpt" => '/' . $redirect[1],
	));
}
$wp->commit();

/**
 * GET POSTS WITH THE WRONG MONTH IN THE URL
 * drupal makes the url w/ the month set by created date, not post date
 * So we're gonna make those old urls point to new good urls
 */

$month_redirects = $d6->prepare('
SELECT DISTINCT n.nid,
r.dst
FROM mjd6.url_alias r
JOIN mjd6.node n
ON ( n.nid = REPLACE(r.src, "node/", "") )
JOIN mjd6.publication_date p
ON ( n.nid = p.nid )
WHERE r.src LIKE "node%" AND r.src NOT LIKE "%feed"
AND n.status = 1 AND 
(n.type = "article" OR n.type = "blogpost" OR n.type = "full_width_article")
AND 
MONTH( FROM_UNIXTIME(p.published_at) )
!=
TRIM( LEADING "0" FROM
  SUBSTRING_INDEX(
	  SUBSTRING_INDEX( r.dst, "/", -2 ),
  "/", 1)
)
;'
);
$month_redirects->execute();

$wp->beginTransaction();
while ( $redirect = $month_redirects->fetch(PDO::FETCH_ASSOC)) {
	$redirect_post_insert->execute(Array(
		"post_name" => md5('/' . $redirect['dst']),
		"post_title" => '/' . $redirect['dst'],
		"post_parent" => '/' . $redirect['nid'],
		"post_excerpt" => null,
	));
}
$wp->commit();


// now do the stuff where we need the wildcards
$redirect_post_insert = $wp->prepare('
insert ignore into pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, post_parent, post_mime_type)
values (
1,
from_unixtime("1970-1-1 00:00:00"),
convert_tz(from_unixtime("1970-1-1 00:00:00"), "pst8pdt","utc"),
"",
"auto draft", #post title
"",
:post_name, #post name
"",
"",
from_unixtime("1970-1-1 00:00:00"),
convert_tz(from_unixtime("1970-1-1 00:00:00"), "pst8pdt","utc"),
"",
"redirect_rule",
"publish",
0, # to as post id
""
)
;
');
$wp->beginTransaction();
$redirect_post_insert->execute(array(
	"post_name" => 'node-to-post',
));
$node_to_post = $wp->lastInsertId();

$redirect_post_insert->execute(array(
	"post_name" => 'rss-feeds',
));
$rss_feeds = $wp->lastInsertId();
$wp->commit();


$meta_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_postmeta
(post_id, meta_key, meta_value)
VALUES (?, ?, ?)
;
');
$wp->beginTransaction();
$meta_insert->execute(array(
	$node_to_post,
	'_redirect_rule_from',
	'/node/*',
));
$meta_insert->execute(array(
	$node_to_post,
	'_redirect_rule_to',
	'/?p=*',
));
$meta_insert->execute(array(
	$node_to_post,
	'_redirect_rule_status_code',
	'302',
));

$meta_insert->execute(array(
	$rss_feeds,
	'_redirect_rule_from',
	'/rss/sections/*/feed',
));
$meta_insert->execute(array(
	$rss_feeds,
	'_redirect_rule_to',
	'/category/*/feed',
));
$meta_insert->execute(array(
	$rss_feeds,
	'_redirect_rule_status_code',
	'302',
));
$wp->commit();
