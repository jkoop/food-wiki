<?php

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\MarkdownConverter;

include_once __DIR__ . "/../vendor/autoload.php";

function getSetting(string $settingName): string|array|null {
	static $settings = null;
	if (is_array($settings)) {
		if (!array_key_exists($settingName, $settings)) {
			die("Setting '$settingName' not set.");
		}
		return $settings[$settingName];
	}

	$serverSettings = json_decode(file_get_contents(__DIR__ . "/../settings.json"), true);

	$wikiSettings = [];
	foreach (json_decode(file_get_contents(__DIR__ . "/../wiki/settings.json"), true) as $name => $value) {
		$wikiSettings["wiki" . ucfirst($name)] = $value;
	}

	$settings = array_merge($serverSettings, $wikiSettings);

	if (!array_key_exists($settingName, $settings)) {
		die("Setting '$settingName' not set.");
	}

	return $settings[$settingName];
}

function getCookie(string $cookieName): string|null {
	static $cookies = null;

	if (is_array($cookies)) {
		return $cookies[$cookieName] ?? null;
	}

	$cookies = [];
	foreach ($_COOKIE as $name => $value) {
		$signature = strtok($value, ":");
		$value = strtok(null);
		if (md5($value . ":" . getSetting("appKey")) != $signature) {
			continue;
		}
		$cookies[$name] = $value;
	}

	return $cookies[$cookieName] ?? null;
}

function isLoggedIn(): bool {
	return getCookie("user") != null;
}

function canView(): bool {
	$userId = strtok(getCookie("user") ?? "", ":");
	return isIconRequested() || isAssetRequested() || in_array($userId, getSetting("wikiViewers"));
}

function canEdit(): bool {
	$userId = strtok(getCookie("user") ?? "", ":");
	return in_array($userId, getSetting("wikiEditors"));
}

function isAssetRequested(): bool {
	return str_Starts_with(getRealPathOfRequestedFile(), realpath(__DIR__ . "/../assets"));
}

function isIconRequested(): bool {
	$logoPath = realpath(__DIR__ . "/../wiki/" . getSetting("wikiIconPath"));
	return getRealPathOfRequestedFile() === $logoPath;
}

function http400(string $message): never {
	http_response_code(400);
	echo $message;
	exit();
}

function http401(): never {
	// https://discord.com/oauth2/authorize?client_id=1111111111111111111&response_type=code&redirect_uri=http%3A%2F%2Fexample.com%2F_login&scope=identify
	$discordUrl =
		"https://discord.com/oauth2/authorize?" .
		http_build_query([
			"client_id" => getSetting("discordClientId"),
			"response_type" => "code",
			"redirect_uri" => trim(getSetting("appUrl"), "/") . "/_login",
			"scope" => "identify",
		]);
	$cacheBuster = filemtime(__DIR__ . "/../assets/discord-logo-white.svg");

	formatAndRespond(
		title: "401 Unauthorized",
		content: <<<HTML
<a href='$discordUrl' id="login-link">
	<img id="discord-logo" src="/discord-logo-white.svg?t=$cacheBuster" />
	Sign in with Discord
</a>
HTML
		,
		status: 401
	);
}

function http403(): never {
	$cacheBuster = filemtime(__DIR__ . "/../assets/persuadable-bouncer-403.jpg");
	formatAndRespond(
		title: "403 Forbidden",
		content: "<h1>Forbidden</h1><br><img src='/persuadable-bouncer-403.jpg?t=$cacheBuster' />",
		status: 403
	);
}

function http404(): never {
	$cacheBuster = filemtime(__DIR__ . "/../assets/where-is-it.jpg");
	formatAndRespond(
		title: "404 Not Found",
		content: "<h1>Not Found</h1><br><img src='/where-is-it.jpg?t=$cacheBuster' />",
		status: 404
	);
}

function formatAndRespond(string $title, string $content, int $status = 200): never {
	http_response_code($status);

	$language = getSetting("wikiLanguage");
	$wikiName = getSetting("wikiName");
	$favicon = getSetting("wikiIconPath");
	$iconUpdatedAt = filemtime(__DIR__ . "/../wiki/" . $favicon) ?? 0;
	strtok(getCookie("user") ?? "", ":");
	$userName = strtok(null) ?: "anonymous";
	require __DIR__ . "/layout.php";
	exit();
}

function e(string $string): string {
	return htmlentities($string, ENT_QUOTES, "utf-8");
}

/**
 * @return string "index" if requesting index page, "login" if Discord callback
 */
function getRealPathOfRequestedFile(): string {
	$requestPath = $_SERVER["REQUEST_URI"];
	$requestPath = str_replace("..", "", $requestPath);
	$requestPath = parse_url("http://example.com/" . $requestPath, PHP_URL_PATH);
	$requestPath = preg_replace("#/+#", "/", $requestPath);

	if ($requestPath == "/") {
		return "index";
	}

	if ($requestPath == "/_login") {
		return "login";
	}

	if ($requestPath == "/logout") {
		return "logout";
	}

	$realPath = __DIR__ . "/../wiki/" . $requestPath;

	if (!file_exists($realPath)) {
		$realPath = __DIR__ . "/../assets/" . $requestPath;
	}

	$realPath = realpath($realPath);
	return $realPath;
}

function getMimetype(string $realPath): string {
	return cache("mimetype:$realPath", filemtime($realPath), function () use ($realPath): string {
		$mimetype = mime_content_type($realPath);
		if ($mimetype == false || $mimetype == "text/plain") {
			$extension = pathinfo($realPath, PATHINFO_EXTENSION);
			return match ($extension) {
				"css" => "text/css",
				default => "application/octet-stream",
			};
		}
		return $mimetype;
	});
}

function cache(string $key, int $minUpdateTime, callable $callback): mixed {
	$pathToCacheFile = __DIR__ . "/../cache/" . md5($key);

	if (!file_exists($pathToCacheFile) || filemtime($pathToCacheFile) < $minUpdateTime) {
		$newValue = $callback();

		if (!is_dir(__DIR__ . "/../cache/")) {
			mkdir(__DIR__ . "/../cache/");
		}

		file_put_contents($pathToCacheFile, serialize($newValue));
		return $newValue;
	}

	return unserialize(file_get_contents($pathToCacheFile));
}

function scaleImage(string $realPath, int $width = null, int $height = null): string {
	if (($width < 1 && $height < 1) || ($width > 0 && $height > 0) || $width < 0 || $height < 0) {
		throw new InvalidArgumentException();
	}

	$imageDimensions = getImageDimensions($realPath);

	if ($width > 0) {
		$newWidth = $width;
		$newHeight = ($width / $imageDimensions["width"]) * $imageDimensions["height"];
	} else {
		$newWidth = ($height / $imageDimensions["height"]) * $imageDimensions["width"];
		$newHeight = $height;
	}

	if ($newWidth >= $imageDimensions["width"] && $newHeight >= $imageDimensions["height"]) {
		return $realPath;
	}

	return cache("scaleImage:$realPath:$newWidth:$newHeight", filemtime($realPath), function () use (
		$realPath,
		$newWidth,
		$newHeight
	): string {
		$mimetype = getMimetype($realPath);
		$format = explode("/", $mimetype)[1];
		if (!in_array($format, ["png", "jpeg", "webp"])) {
			$format = "webp";
		}

		$outputPath = __DIR__ . "/../cache/" . md5("scaleImage:$realPath:$newWidth:$newHeight:image");
		$command = sprintf(
			"convert %s -resize %dx%d -quality 50 %s:%s",
			escapeshellarg($realPath),
			$newWidth,
			$newHeight,
			escapeshellarg($format),
			escapeshellarg($outputPath)
		);
		exec($command);
		return realpath($outputPath);
	});
}

function getImageDimensions(string $realPath): array {
	return cache("imageDimensions:$realPath", filemtime($realPath), function () use ($realPath): array {
		$imageSize = getimagesize($realPath);
		$width = $imageSize[0];
		$height = $imageSize[1];
		return compact("width", "height");
	});
}

function respondForMedia(string $realPath): never {
	if (!file_exists($realPath)) {
		http404();
	}

	$mimetype = getMimetype($realPath);

	if (str_starts_with($mimetype, "image/")) {
		if (($_GET["width"] ?? null) > 0) {
			$realPath = scaleImage($realPath, width: $_GET["width"]);
		} elseif (($_GET["height"] ?? null) > 0) {
			$realPath = scaleImage($realPath, height: $_GET["height"]);
		}
		$mimetype = getMimetype($realPath);
	}

	$realPath = substr($realPath, strlen(realpath(__DIR__ . "/..")));

	header("Content-Type: $mimetype");
	header("X-Accel-Redirect: $realPath");
	exit();
}

function respondForIndex(): never {
	$fragments = glob(__DIR__ . "/../wiki/*.md");
	natcasesort($fragments);
	$fragments = array_map(function (string $path): string {
		$pieces = explode("/", $path);
		$name = array_pop($pieces);
		$name = substr($name, 0, -3);
		return realpath($path);
	}, $fragments);

	// we add this heading so the comment above the first real heading is kept
	$content = "# bogus heading";
	foreach ($fragments as $realPath) {
		$wikiPath = substr($realPath, strlen(realpath(__DIR__ . "/../wiki")) + 1);
		// this comment is used by the code below that generates the edit links
		$content .= "\n\n<!--" . e($wikiPath) . "-->\n\n";
		$content .= file_get_contents($realPath);
	}

	// $content = "<p><b>Contents:</b></p>" . markdown2html($content);
	$content = markdown2html($content);
	formatAndRespond("Home", $content);
}

function markdown2html(string $markdown): string {
	static $config = [
		"heading_permalink" => [
			"html_class" => "heading-link",
			"id_prefix" => "",
			"apply_id_to_heading" => false,
			"heading_class" => "",
			"fragment_prefix" => "",
			"insert" => "after",
			"min_heading_level" => 1,
			"max_heading_level" => 1,
			"title" => "Sharable Link",
			"symbol" => "🔗",
			"aria_hidden" => true,
		],
	];

	$environment = new Environment($config);
	$environment->addExtension(new CommonMarkCoreExtension());
	$environment->addExtension(new GithubFlavoredMarkdownExtension());
	$environment->addExtension(new HeadingPermalinkExtension());
	$environment->addExtension(new SmartPunctExtension());
	// $environment->addExtension(new TableOfContentsExtension());

	$converter = new MarkdownConverter($environment);
	$html = $converter->convert($markdown);

	$document = new DOMDocument();
	$document->loadHTML(convertUtf8ToHtmlEntities($html));

	$xpath = new DOMXPath($document);
	$images = $xpath->query("//img");

	foreach ($images as $image) {
		$image->setAttribute("class", "float");
		$image->setAttribute("width", "200");
		$image->setAttribute("loading", "lazy");

		$lastModified = null;
		$src = $image->getAttribute("src");
		$realPath = realpath(__DIR__ . "/../wiki/" . $src);
		if (!preg_match("#[a-z]+://#i", $src) && !str_contains($src, "..") && $realPath != false) {
			$dimensions = getImageDimensions($realPath);
			$lastModified = filemtime($realPath);

			$width = $dimensions["width"];
			$height = $dimensions["height"];

			$image->setAttribute("src", $src . "?width=500&t=" . $lastModified);
			$image->setAttribute("height", round((200 / $width) * $height));
		}

		$link = $document->createElement("a");
		if ($lastModified > 0) {
			$link->setAttribute("href", $src . "?t=" . $lastModified);
		} else {
			$link->setAttribute("href", $src);
		}

		$image->before($link);
		$link->append($image);
	}

	$fragmentLinks = $xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," heading-link ")]');
	foreach ($fragmentLinks as $fragmentLink) {
		$heading = $fragmentLink->parentElement;
		$comment = $heading->previousSibling;
		while ($comment !== null && !$comment instanceof DOMComment) {
			$comment = $comment->previousSibling;
		}

		if ($comment == null) {
			// then this is the bogus heading
			$heading->remove();
			continue;
		}

		$editLink = $document->createElement("a");
		$editLink->setAttribute("class", "edit-link");
		$editLink->setAttribute("href", "/edit/" . $comment->textContent);
		$editLink->appendChild($document->createTextNode("📝"));
		$fragmentLink->after($editLink);

		$fragmentLink->before($document->createTextNode(" "));
		$fragmentLink->after($document->createTextNode(" "));
	}

	$body = $document->getElementsByTagName("body")->item(0);
	return $document->saveHTML($body);
}

function convertUtf8ToHtmlEntities(string $html): string {
	// $html = mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8"); // deprecated
	$new = "";
	$htmlLen = strlen($html);

	for ($i = 0; $i < $htmlLen; ) {
		$char = $html[$i++];
		$byte = ord($char);

		// https://en.wikipedia.org/wiki/UTF-8#Encoding
		if ($byte <= 0b01111111) {
			$new .= $char;
			continue;
		}
		if ($byte >= 0b11110000) {
			$char .= $html[$i++];
		}
		if ($byte >= 0b11100000) {
			$char .= $html[$i++];
		}
		if ($byte >= 0b11000000) {
			$char .= $html[$i++];
		}
		$new .= "&#" . mb_ord($char) . ";";
	}

	return $new;
}

function login(): never {
	$code = $_GET["code"] ?? http400('The query parameter "code" is required');

	$response = @file_get_contents(
		"https://discord.com/api/oauth2/token",
		context: stream_context_create([
			"http" => [
				"header" => "Content-type: application/x-www-form-urlencoded\r\n",
				"method" => "POST",
				"content" => http_build_query([
					"client_id" => getSetting("discordClientId"),
					"client_secret" => getSetting("discordClientSecret"),
					"grant_type" => "authorization_code",
					"code" => $code,
					"redirect_uri" => trim(getSetting("appUrl"), "/") . "/_login",
				]),
			],
		])
	);
	if ($response === false) {
		http400('The query parameter "code" is invalid');
	}

	$accessToken = json_decode($response)->access_token ?? die("Discord gave a bad response (access token)");

	$response = @file_get_contents(
		"https://discord.com/api/v10/users/@me",
		context: stream_context_create([
			"http" => [
				"header" => "Authorization: Bearer $accessToken\r\n",
			],
		])
	);
	if ($response === false) {
		die("Couldn't get user data from Discord");
	}

	$user = json_decode($response) ?? die("Discord gave a bad response (user data)");
	$name = $user->global_name ?? $user->username . "#" . $user->discriminator;
	$name = transliterate($name);
	$user = $user->id . ":" . $name;
	$user = md5($user . ":" . getSetting("appKey")) . ":" . $user;
	setcookie("user", $user, time() + 60 * 60 * 24 * 14, "/", secure: true, httponly: true);
	header("Location: /");
	exit();
}

function logout(): never {
	setcookie("user", "", time() - 1);
	header("Location: /");
	exit();
}

function transliterate(string $string): string {
	$string = \Transliterator::create("NFKC; [:Nonspacing Mark:] Remove; NFKC; Any-Latin; Latin-ASCII;")->transliterate(
		$string
	);
	$string = mb_convert_encoding($string, "ascii");
	return $string;
}
