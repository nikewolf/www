<?php

/**
 * This script allows to save pictures into server,
 * and backup chat messages from several services.
 */

//Config
$Config['GateURL'] = "/gate/";
$Config['GateFor'] = 'skelos';
$Config['PicsFolder'] = 'galeries/incoming';

//Only GateFor address can access it.
if ($_SERVER["REMOTE_ADDR"] != gethostbyname ($Config['GateFor'])) {
	die("This gate is restricted to another host.");
}

//Gets URL fragments
$url = explode('/', substr($_SERVER["REQUEST_URI"], strlen($Config['GateURL'])));

//Helper functions

//Grabs an image
function grab_image($url, $filename){
    $ch = curl_init ($url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
    $raw=curl_exec($ch);
    curl_close ($ch);
    $fp = fopen($filename,'w');
    fwrite($fp, $raw);
    fclose($fp);
}

/**
 * Gets all metatags, including those using meta property= and meta itemprop= syntax
 *
 * @return array an array where the keys are the metatags' names and the values the metatags' values
 */
function get_all_meta_tags ($url) {
    $metaTags = array();
    if ($data = file_get_contents($url)) {
        //Thank you to Michael Knapp and Mariano
        //See http://php.net/manual/en/function.get-meta-tags.php comments
        preg_match_all('/<[\s]*meta[\s]*\b(name|property|itemprop)\b="?' . '([^>"]*)"?[\s]*' . 'content="?([^>"]*)"?[\s]*[\/]?[\s]*>/si', $data, $match);

        if (isset($match) && is_array($match) && count($match) == 4) {
            $originals = $match[0];
            $names = $match[2];
            $values = $match[3];

            if (count($originals) == count($names) && count($names) == count($values)) {
                $metaTags = array();

                for ($i=0, $limiti = count($names) ; $i < $limiti ; $i++) {
                    $metaTags[$names[$i]] = $values[$i];
                }
            }
        }
    }
    return $metaTags;
}


//Determines if the file is a picture
function is_picture ($file) {
	$ext = substr(strtolower($file), -4);
	return $ext == ".jpg" || $ext == ".png";
}

//Gets unixtime from a planetromeo date
function parse_planetromeo_date ($date) {
        $tz = new DateTimeZone('Europe/Brussels');
        if ($dt = DateTime::createFromFormat('j M Y H:i', $date, $tz)) {
		return $dt->getTimestamp();
	}
	return 0;
}

function save_message_to_blackbox ($source) {
	require_once('_includes/mysql.php');
	if (!isset($_POST['message'])) {
		die("No message.");
		break;
	}
	$msg = json_decode($_POST['message']);

	$date = ($source == 'planetromeo') ? parse_planetromeo_date($msg->date) : strtotime($msg->date);
	$author = $db->sql_escape($msg->author->name);
	$text = $db->sql_escape($msg->text);
	$author_id = $msg->author->id;
	$sql = "REPLACE INTO Blackbox_messages
		(message_source, message_remote_id, message_remote_contact_name, message_remote_contact_id, message_date, message_direction, message_text)
		VALUES ('$source', $msg->id, '$author', $author_id, $date, '$msg->direction', '$text');";

	if (!$db->sql_query($sql)) {
		print_r($msg);
		die("SQL error: $sql");
	}
}

//Gate
switch ($url[0]) {
	case 'planetromeo':
		save_message_to_blackbox('planetromeo');
		break;

	case 'sneakersgate':
		save_message_to_blackbox('sneakersgate');
		break;

	case 'tumblr':
		$url_post = isset($_REQUEST['URL']) ? urldecode($_REQUEST['URL']) : (isset($url[1]) ? urldecode($url[1]) : '');

		//Prints form if no URL is specified
		if (!$url_post) {
			echo <<<'EOD'
<form method="post">
	<label for="URL"></label><input id="URL" name="URL" size=80 /><input type="submit" />
</form>
EOD;
			exit;
		}

		//Gets image URL
		if (is_picture($url_post)) {
			$url_img = $url_post;
		} else {
			$meta_tags = get_all_meta_tags($url_post);
			if (isset($meta_tags['twitter:image'])) {
				$url_img = $meta_tags['twitter:image'];
			} else {
				die("No image found at $url_post.");
			}
		}

		//Saves it
		$url_data = explode('/', $url_img);
		$filename = array_pop($url_data);
		$target = "$Config[PicsFolder]/$filename";

		if (file_exists($target)) {
			die("We already should have it.<h2>The local picture</h2><img src='/$target' /><h2>The picture you want to smuggle in</h2><img src='$url_img' />");
		}

		grab_image($url_img, $target);
		die("<H2>Image saved</H2><img src='$url_img' />");
	break;

	case '':
		die("What door would you want to enter?");

	default:
		die("Unknown door: $url[0]");
}
