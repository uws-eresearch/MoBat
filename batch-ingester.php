<?php
require_once 'libraries/tuque/FedoraApi.php';
require_once 'libraries/tuque/Repository.php';
require_once 'libraries/tuque/Cache.php';
require_once 'libraries/tuque/FedoraApiSerializer.php';
require_once 'libraries/tuque/FedoraDate.php';
require_once 'libraries/tuque/FedoraRelationships.php';
require_once 'simpleimage.php';

if (isset($argv)) {
	$arg_dir = isset($argv[1]) ? $argv[1] : "";
	$arg_action = isset($argv[2]) ? $argv[2] : "";
	$start_id = isset($argv[3]) ? $argv[3] : "";
	$end_id = isset($argv[4]) ? $argv[4] : "";
}


$fedoraUrl = "http://115.146.93.105:8080/fedora";
// $fedoraUrl = "http://localhost:8080/fedora";
$username = "fedoraAdmin";
$password = "fedoraAdmin";

$connection = new RepositoryConnection($fedoraUrl, $username, $password);
$connection->reuseConnection = TRUE;

$repository = new FedoraRepository(new FedoraApi($connection), new SimpleCache());

$content_models = array(array('pid' => 'islandora:collectionCModel'));

$namespace = 'islandora';
$collection_pid = 'islandora:sp_adelta';

if($arg_action == "delete"){
	
	for ($i = $start_id; $i <= $end_id; $i++) {
		try {
			//$obj = $repository->getObject($namespace . ':' . $i);
			$repository->purgeObject($namespace . ':' . $i);
		}
		catch(RepositoryException $e){
			continue; //Skip errorneous object and run until end_id is reached.
		}
	}
	return;
}

$files = glob($arg_dir.'/*.{xml}', GLOB_BRACE);
foreach($files as $file) {
	//we need title, thumbnail
	try {
		
		$xml_source = str_replace("&", "&amp;", file_get_contents($file));
		//$xml = simplexml_load_string($xml_source);
		//$title = (string) $xml->titleInfo->title;
		
		
		$dom = new DOMDocument;
		$dom->loadXML($xml_source);
		$titles = $dom->getElementsByTagName('title');
		foreach ($titles as $title){
			$titleVal = $title->nodeValue;
		}
		
		//Check whether description is available. If not add "To be completed by project leads"
		$desc_elements = $dom->getElementsByTagName('abstract');
		foreach ($desc_elements as $desc){
			if($desc->attributes->getNamedItem("type")->nodeValue == "description"){
				if(empty($desc->nodeValue)){
					$desc->nodeValue = "To be completed by project leads";
					break;
				}
			}
		}
		
		$imgDir_elements = $dom->getElementsByTagName('imageDir');
		
		foreach ($imgDir_elements as $img){
			$image_dir = $img->nodeValue;
			// remove the image_dir element from dom.
			$img->parentNode->removeChild($img);
			//There's only one image element. So save the file.
			$dom->save($file);
		}
		
		$path_parts = pathinfo($file);
		
		//$thumbnail = glob($arg_dir.'/'.$path_parts['filename'].'.{jpg,jpeg,png,gif}', GLOB_BRACE);
		/*$thumbnail = glob($arg_dir.'/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
		
		//Check whether there is any image in the image folder. If so get'em all create datastreams for all
		
		if(count($thumbnail) == 0){
			echo "No image. Skipping record id ".$path_parts['filename'] . PHP_EOL;
			continue;
		}*/
	
		$fedora_object = $repository->constructObject($namespace); // allow fedora to generate a PID
		
		$fedora_object->models = array('islandora:collectionCModel');
		
		$fedora_object->label = $titleVal;
		$fedora_object->owner = trim(shell_exec('whoami'));
		
		
		//This is where we need to create datastreams. Make sure to change image folder path.
		
		$images = glob($arg_dir.'/Images/'.$image_dir.'/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
		
		if(count($images) == 0){
			echo "No images. Skipping record id ".$path_parts['filename'] . PHP_EOL;
			continue;
		}
		
		$id = "IMG";
		$j = 0;
		//We need to assign an id for other image datastreams
		foreach ($images as $image){
			//find the file name
			$img_path_parts = pathinfo($image);
			if($img_path_parts['filename'] == "front-page" || $img_path_parts['filename'] == "Front-page" ||
					$img_path_parts['filename'] == "Front-Page"){
				
				//First we need to create OBJ datastream from front-page image
				createImageDatastream("OBJ", $image, $fedora_object);
				
				$tn_path = scaleImages("TN", $image);	
				if(isset($tn_path)){
					createImageDatastream("TN", $tn_path, $fedora_object);
				}
				else{
					print "Error creating thumbnail image" . PHP_EOL;
					continue 2;
				}

				$medium_path = scaleImages("Medium", $image);
				createImageDatastream("MEDIUM_SIZE", $medium_path, $fedora_object);
			}
			else{
				$id = "IMG".++$j;
				createImageDatastream($id, $image, $fedora_object);
			}
		}
		
		
		
		
		//TODO Create MEDIUM datastream as well - User needs to be able to specify the image path and csv file
		/*$datastream_id = "TN";
		$new_datastream = $fedora_object->constructDatastream($datastream_id);
		$image_path = $thumbnail[0];
		$new_datastream->label = 'TN';
		$new_datastream->mimetype = 'image/jpeg';
		$new_datastream->setContentFromFile($image_path);
		$fedora_object->ingestDatastream($new_datastream);
		
		//If 
		
		$datastream_id = "MEDIUM_SIZE";
		$new_datastream = $fedora_object->constructDatastream($datastream_id);
		$image_path = $thumbnail[0];
		$new_datastream->label = 'MEDIUM_SIZE';
		$new_datastream->mimetype = 'image/jpeg';
		$new_datastream->setContentFromFile($image_path);
		$fedora_object->ingestDatastream($new_datastream);*/
		
		//TODO get separate mods xml file for each record
		$datastream_id = "MODS";
		$new_datastream = $fedora_object->constructDatastream($datastream_id);
		$mods_path = $file;
		$new_datastream->label = 'MODS record';
		$new_datastream->mimetype = 'text/xml';
		$new_datastream->setContentFromFile($mods_path);
		$fedora_object->ingestDatastream($new_datastream);
		
		$fedora_object->relationships->remove(FEDORA_MODEL_URI, 'hasModel', 'islandora:collectionCModel');
		$fedora_object->relationships->add(FEDORA_MODEL_URI, 'hasModel', 'islandora:sp_adelta');
		
		$fedora_object->relationships->add(FEDORA_RELS_EXT_URI, 'isMemberOfCollection', 'islandora:sp_adelta_collection');
		
		//$fedora_object['your dsid']->relationships->remove(ISLANDORA_SCHOLAR_EMBARGO_RELS_URI, 'embargo-until');
		//$fedora_object['your dsid']->relationships->add(ISLANDORA_SCHOLAR_EMBARGO_RELS_URI, 'embargo-until', 'some date', RELS_TYPE_DATETIME);
	
		$repository->ingestObject($fedora_object);
	
	} catch (Exception $e) {
		print "Error occured" . PHP_EOL;
		continue;
	}
}

//This should return a thumbnail image
function scaleImages($type, $image_file) {
	
	//$image = new SimpleImage();
	
	/*If we used imagemagick,
	$image = new Imagick();
	$image->setOption('jpeg:size', '800x532');
	$image->readImage('foo.jpg');
	
	create thumbnail
	$image->thumbnailImage($newX,$newY);
	
	write to file
	$image->writeImage($thumbnailFilename);*/
	
	
	$size = getImageSize($image_file);
	$parts = pathinfo($image_file);
	$image_file = escapeshellarg($image_file);
	
	if($type == "TN"){
		$path_suffix = "_tn.";
		$width = 200;
		$height = 150;
		$base_cmd = "convert -define jpeg:size=200x200 " . $image_file . " -thumbnail '150x150^' -background indigo -gravity center -extent 150x150 ";
	}
	elseif ($type == "Medium"){
		$path_suffix = "_medium.";
		$width = 400;
		$height = 300;
		$base_cmd = "convert " . $image_file . " -resize 460x345\> ";
	}
	
	$processed_dir = $parts['dirname'].'/processed/';
	if(is_dir($processed_dir)){
		$ok = true;
	}
	else {
		$ok = mkdir($processed_dir);
	}
	if($ok)
	{
		$processed_path = $processed_dir.$parts['filename'].$path_suffix.$parts['extension'];
		$destination = escapeshellarg($processed_path);
	
		/*if ($size[0] > $width) {
			$image->load($image_file);
			$image->resizeToWidth($width);
			$image->save($processed_path);
		} 
		else {
			$image->load($image_file);
			$image->save($processed_path);
		}
			
		$size = getImageSize($processed_path);
		if ($size[1] > $height) {
			$image->load($processed_path);
			$image->resizeToHeight($height);
			$image->save($processed_path);
		}*/
		
		//Use imagemagic instead. Just execute a command
		$cmd = $base_cmd . $destination;
		exec($cmd);
		
		return $processed_path;
	}
	else {
		return null;
	}
	
	
	
}

//A funtion to create image datastreams
function createImageDatastream($type, $image_path, $object) {
	//$datastream_id = $type;
	$new_datastream = $object->constructDatastream($type, 'M');
	//$image_path = $thumbnail[0];
	$parts = pathinfo($image_path);
	if($type == 'OBJ') {
		$new_datastream->label = $parts['basename'];
	}
	else {
		$new_datastream->label = $type;
	}
	
	$new_datastream->mimetype = 'image/' . $parts['extension'];
	$new_datastream->setContentFromFile($image_path);
	$object->ingestDatastream($new_datastream);
}


/*$fedora_object = $repository->getObject($pid);

if (!$fedora_object) {
	drupal_set_message("Fedora Object isn't in the repo!");
}
else {
	print_r($fedora_object['models']);
}*/

