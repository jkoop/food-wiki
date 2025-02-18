<?php require_once __DIR__ . "/functions.php"; ?>
<!DOCTYPE html>
<html lang="<?= e($language) ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e($title) ?> - <?= e($wikiName) ?></title>
    <?php for ($size = 16; $size < 257; $size *= 2): ?>
        <link rel="icon" sizes="<?= $size ?>x<?= $size ?>" href="<?= e(
	$favicon
) ?>?height=<?= $size ?>&t=<?= $iconUpdatedAt ?>" />
    <?php endfor; ?>
    <link rel="stylesheet" href="<?= assetHref("style.css") ?>" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,100..900;1,100..900&display=swap" />
</head>
<body>
    <nav><a href="/"><img src="<?= e($favicon) ?>?height=160&t=<?= $iconUpdatedAt ?>" height="64" /></a></nav>
    <main><?= $content ?></main>
    <footer><hr><?= e($userName) ?> - <a href="/logout">Log out</a></footer>
</body>
</html>