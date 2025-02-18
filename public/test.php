<!doctype html>
<meta name="viewport" content="width=device-width, initial-scale=1" />

<?php
include_once "../app/functions.php";

if ($_POST != []) {
	echo "<pre>";
	var_dump($_POST);
	echo "</pre>";

	echo "<pre>";
	var_dump($_FILES);
	echo "</pre>";

	echo "<pre>";
	echo e(markdown2html($_POST["content"]));
	echo "</pre>";

	exit();
}
?>

<form method="post" id="edit-form" enctype="multipart/form-data">
    <textarea style="display:block" name="content">Ahoy!</textarea>

    <button id="add-button" type="button">Add image</button>
    <input id="file-input" style="display:none" type="file" accept="<?= implode(",", ACCEPTABLE_IMAGES) ?>" multiple />
    <ul id="file-list"></ul>

    <button type="submit">Save</button>
</form>

<script type="module" src="<?= assetHref("editor.js") ?>"></script>