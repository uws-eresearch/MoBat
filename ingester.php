<?php
require_once 'libraries/tuque/FedoraApi.php';
require_once 'libraries/tuque/Repository.php';
require_once 'libraries/tuque/Cache.php';
require_once 'libraries/tuque/FedoraApiSerializer.php';
require_once 'libraries/tuque/FedoraDate.php';
require_once 'libraries/tuque/FedoraRelationships.php';

if (isset($argv)) {
	$arg_title = isset($argv[1])?$argv[1]:"";
	$arg_mods = isset($argv[2])?$argv[2]:"";
	$arg_thumbnail = isset($argv[3])?$argv[3]:"";
	$arg_medium = isset($argv[4])?$argv[4]:"";
}

$fedoraUrl = "http://localhost:8080/fedora";
$username = "fedoraAdmin";
$password = "fedAdmin";

$connection = new RepositoryConnection($fedoraUrl, $username, $password);
$connection->reuseConnection = TRUE;

$repository = new FedoraRepository(new FedoraApi($connection), new SimpleCache());

$content_models = array(array('pid' => 'islandora:collectionCModel'));

$namespace = 'islandora';
$collection_pid = 'islandora:sp_adelta';

$fedora_object = $repository->constructObject($namespace); // allow fedora to generate a PID

$fedora_object['models'] = array('islandora:collectionCModel');

$fedora_object->label = $arg_title;
$fedora_object->owner = trim(shell_exec('whoami'));

//TODO Create MEDIUM datastream as well - User needs to be able to specify the image path and csv file
$datastream_id = "TN";
$new_datastream = $fedora_object->constructDatastream($datastream_id);
$image_path = $arg_thumbnail;
$new_datastream->label = 'TN';
$new_datastream->mimetype = 'image/jpeg';
$new_datastream->setContentFromFile($image_path);
$fedora_object->ingestDatastream($new_datastream);

$datastream_id = "MEDIUM_SIZE";
$new_datastream = $fedora_object->constructDatastream($datastream_id);
$image_path = $arg_medium;
$new_datastream->label = 'MEDIUM_SIZE';
$new_datastream->mimetype = 'image/jpeg';
$new_datastream->setContentFromFile($image_path);
$fedora_object->ingestDatastream($new_datastream);

//TODO get separate mods xml file for each record
$datastream_id = "MODS";
$new_datastream = $fedora_object->constructDatastream($datastream_id);
$mods_path = $arg_mods;
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

/*$fedora_object = $repository->getObject($pid);

if (!$fedora_object) {
	drupal_set_message("Fedora Object isn't in the repo!");
}
else {
	print_r($fedora_object['models']);
}*/

