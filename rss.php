<?php
// Отдаём правильный заголовок
header('Content-type: application/rss+xml');

include_once 'common.php';

$offers = array();
$categories_list = array();

// Готовим обращение к API
//------------------------------------------------------------------------------
$cache_key_categories = $Cache->PrepareCacheKey(array(
	'for' => 'categories',
	'lang' => $settings['lang'],
));
if (!$categories_list = $Cache->Get($cache_key_categories)) {
	$APIAccess->AddRequestCategoriesList('categories', $settings['lang']);
}
//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
$search_params = array(
		'query' => '',
		'offset' => 0,
		'limit' => $settings['rss_items_count'],
		'orderby' => 'added_at',
		'order_direction' => 'desc',
		'price_min' => $settings['price_min'],
		'price_max' => $settings['price_max'],
		'lang' => $settings['lang'],
		'currency' => $settings['currency'],
	);

$cache_key_search = $Cache->PrepareCacheKey($search_params);

$offers_tmp = FALSE;
if (!$offers_tmp = $Cache->Get($cache_key_search)) {
	$APIAccess->AddRequestSearch('search', $search_params);
}
else {
	$offers = $offers_tmp['offers'];
	$total_offers = $offers_tmp['total_found'];
}
//------------------------------------------------------------------------------

// Выполняем запрос к API
if ($APIAccess->RunRequests()) {
	// Достаём данные
	if (($categories_list_tmp = $APIAccess->GetRequestResult('categories')) && isset($categories_list_tmp['categories'])) {
		$categories_list = $categories_list_tmp['categories'];
		$Cache->Set($cache_key_categories, $categories_list, 86400);
	}
	// Достаём данные
	if (($offers_tmp = $APIAccess->GetRequestResult('search')) && isset($offers_tmp['offers'])) {
		$offers = $offers_tmp['offers'];
		$Cache->Set($cache_key_search, $offers_tmp, 120);
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

// Здесь будет хэш id => info
$categories_hash = array();
// Дополняем данные
foreach ($categories_list as $key => $value) {
	$categories_list[$key]['link'] = $Path->Category($value['id'], $value['title']);
	$categories_list[$key]['current'] = FALSE;
	$categories_hash[$value['id']] = $categories_list[$key];
}


// Дополняем информацию о товарах
foreach ($offers as $key => $value) {
	// Информация о категории
	$offers[$key]['category'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['title'] : '';
	$offers[$key]['category_link'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['link'] : '';
	// "Прямая" ссылка
	$offers[$key]['url'] = "http://" . $_SERVER['HTTP_HOST'] . $Path->Go($value['id']);
	// Ссылка на более подробную информацию
	$offers[$key]['link'] = "http://" . $_SERVER['HTTP_HOST'] . $Path->Offer($value['id'], $value['name']);
	$offers[$key]['description'] = '';
	// Описание
	if ($value['picture'] != '') {
		$offers[$key]['description'] .= "<p><img src=\"" . htmlspecialchars($value['picture']) . "\" style=\"width: 30%\" /></p>\n";
	}
	$offers[$key]['description'] .= "<p>" . $Lang->GetString('Price') .": " . htmlspecialchars($value['price'] . ' ' . $value['currency']) . "</p>\n";
	$offers[$key]['guid'] = $settings['lang'] . $value['id'];
}


// Цепляем шаблон
include_once $Common->GetTemplatePath('rss');
