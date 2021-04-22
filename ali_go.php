<?php

include_once 'common.php';

// Получаем идентификатор
$id = isset($_GET['id']) ? $_GET['id'] : 0;

// Сюда мы будем редиректить пользователя
$redirect_url = '/';

$offer_info = FALSE;

// Готовим обращение к API
//------------------------------------------------------------------------------
$cache_key_offer_info = $Cache->PrepareCacheKey(array(
	'for' => 'offer',
	'lang' => $settings['lang'],
	'currency' => $settings['currency'],
	'id' => $id
));
if (!$offer_info = $Cache->Get($cache_key_offer_info)) {
	$APIAccess->AddRequestGetOfferInfo('info', $id, $settings['lang'], $settings['currency']);
}
//------------------------------------------------------------------------------

// Выполняем запрос к API
if ($APIAccess->RunRequests()) {
	// Достаём данные
	if (($offer_info_tmp = $APIAccess->GetRequestResult('info')) && isset($offer_info_tmp['offer'])) {
		$offer_info = $offer_info_tmp['offer'];
		$Cache->Set($cache_key_offer_info, $offer_info, 86400);
	}
}
// Если что-то пошло не так и ошибка в данных, а не сетевая
elseif ($APIAccess->LastErrorType() == 'data') {
	print "Проблема при обращении к API ePN: <br />\n";
	print $APIAccess->LastError() . "\n";
	exit();
}
// Если что-то пошло не так и ошибка сетевая
elseif ($APIAccess->LastErrorType() == 'network') {
	print "Сетевая проблема при обращении к API ePN: <br />\n";
	print $APIAccess->LastError() . "\n";
	exit();
}



// Если информация о товаре была получена
if ($offer_info) {
	// Используем реферальный урл
	$redirect_url = $Path->PrepareRefUrl($offer_info['url']);
}

// Выполняем редирект
Header("Location: $redirect_url", TRUE, 302);

// Для особо тупых браузеров отдадим ещё и немного данных
print "<html>\n<head>\n";
// Некоторые поймут так
print "<meta http-equiv=\"refresh\" content=\"3; url=" . htmlspecialchars($redirect_url) . "\">\n";
print "</head>\n<body>\n";
// А некоторым может понадобиться и вот такой велосипед
print "Page moved <a id=\"mainlink\" href=\"" . htmlspecialchars($redirect_url) . "\">here</a>.";
print "<script type=\"text/javascript\">\n";
print "window.location.href = document.getElementById(\"mainlink\").href;\n";
print "</script>\n";
print "</body>\n</html>\n";
