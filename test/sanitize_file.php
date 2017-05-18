<?php

function sanititize_file_in_url( $filepath ) {
	if ( preg_match('/^(.*\/)(.+)$/', $filepath, $matches) ) {
		$file = sanitize_file_name(urldecode($matches[2])); //ha matches 0 is the whole thing of course
		$filepath = $matches[1] . urlencode($file);
	} else {
		$filepath = sanitize_file_name($filepath);
	}
	return $filepath;
}

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

$filename=$argv[1];

print sanititize_file_in_url($filename);
