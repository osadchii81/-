<?php

include_once 'common.php';

// Получаем идентификатор
$id = isset($_GET['id']) ? $_GET['id'] : 0;

$categories_list = array();
$offer_info = FALSE;
$offers = array();

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

//------------------------------------------------------------------------------
$search_params = array(
		'query' => '',
		'offset' => 0,
		'limit' => 3,
		'orderby' => 'rand',
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
	if (($offer_info_tmp = $APIAccess->GetRequestResult('info')) && isset($offer_info_tmp['offer'])) {
		$offer_info = $offer_info_tmp['offer'];
		$Cache->Set($cache_key_offer_info, $offer_info, 86400);
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



// Если информация о товаре была получена
if ($offer_info) {
	// Дополняем данные
	// "Прямая" ссылка
	$offer_info['url'] = $Path->Go($offer_info['id']);
	// Информация о категории
	$offer_info['category'] = isset($categories_hash[$offer_info['id_category']]) ? $categories_hash[$offer_info['id_category']]['title'] : '';
	$offer_info['category_link'] = isset($categories_hash[$offer_info['id_category']]) ? $categories_hash[$offer_info['id_category']]['link'] : '';
	
	// Дополняем информацию о товарах
	foreach ($offers as $key => $value) {
		// Информация о категории
		$offers[$key]['category'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['title'] : '';
		$offers[$key]['category_link'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['link'] : '';
		// "Прямая" ссылка
		$offers[$key]['url'] = $Path->Go($value['id']);
		// Ссылка на более подробную информацию
		$offers[$key]['link'] = $Path->Offer($value['id'], $value['name']);
	}

}



// Цепляем шаблон
include_once $Common->GetTemplatePath('good');
