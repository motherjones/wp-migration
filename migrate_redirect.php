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
"",
"Auto Draft"
"",
:post_name,
"",
"",
FROM_UNIXTIME("1970-1-1 00:00:00"),
CONVERT_TZ(FROM_UNIXTIME("1970-1-1 00:00:00"), "PST8PDT","UTC"),
"",
"redirect_rule",
"publish",
NULL,
""
)
;
');

$redirects = Array();

$wp->beginTransaction();
while ( $redirect = $legacy_redirects->fetch(PDO::FETCH_ASSOC)) {
	$redirect_post_insert->execute(Array(
		"post_name" => $redirect['src']
	));
	$redirects[$wp->lastInsertId()] = $redirect;
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
		"post_name" => $redirect['source']
	));
	$redirects[$wp->lastInsertId()] = Array( 
		'src' => $redirect['source'],
		'dst' => $redirect['redirect']
	);
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
		"post_name" => $redirect[0]
	));
	$redirects[$wp->lastInsertId()] = Array( 
		'src' => $redirect[0],
		'dst' => $redirect[1]
	);
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
		"post_name" => $redirect[0]
	));
	$redirects[$wp->lastInsertId()] = Array( 
		'src' => $redirect[0],
		'dst' => $redirect[1]
	);
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
		"post_name" => $redirect['dst']
	));
	$redirects[$wp->lastInsertId()] = Array( 
		'src' => $redirect['dst'],
		'dst' => '/?p=' . $redirect['nid'],
	);
}
$wp->commit();

$redirect_postmeta_insert = $wp->prepare("
INSERT IGNORE INTO pantheon_wp.wp_postmeta (post_id, meta_key, meta_value)
VALUES ( ?, ?, ? )
;
");

$wp->beginTransaction();
foreach ( $redirects as $id => $redirect ) {
	$redirect_postmeta_insert->execute(Array(
		$id,
		'_redirect_rule_from',
		$redirect['src']
	));
	$redirect_postmeta_insert->execute(Array(
		$id,
		'_redirect_rule_to',
		$redirect['dst']
	));
	$redirect_postmeta_insert->execute(Array(
		$id,
		'_redirect_rule_status_code',
		'301'
	));
}
$wp->commit();
