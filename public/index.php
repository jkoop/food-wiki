<?php

require_once __DIR__ . "/../app/functions.php";

$realPath = getRealPathOfRequestedFile();

if ($realPath == false) {
	http404();
}

if ($realPath == "login") {
	login();
}

if ($realPath == "logout") {
	logout();
}

if (!canView()) {
	if (isLoggedIn()) {
		http403();
	} else {
		http401();
	}
}

if ($realPath == "index") {
	respondForIndex();
}

// it's an image or something
respondForMedia($realPath);
