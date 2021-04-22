<?php

include_once 'common.php';

// Получаем поисковый запрос
$search_query = isset($_GET['q']) ? $_GET['q'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 0;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
if ($page < 1) $page = 1;

$categories_list = array();
$offers = array();
$total_offers = 0;
$search_categories_count = array();
$search_count_hash = array();


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
		'query' => $search_query,
		'limit' => $settings['items_per_page'],
		'offset' => ($page-1)*$settings['items_per_page'],
		'price_min' => $settings['price_min'],
		'price_max' => $settings['price_max'],
		'category' => ($category > 0) ? $category : '',
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

//------------------------------------------------------------------------------
$search_count_params = array(
		'query' => $search_query,
		'price_min' => $settings['price_min'],
		'price_max' => $settings['price_max'],
		'lang' => $settings['lang'],
	);
	
$cache_key_search_count = $Cache->PrepareCacheKey($search_count_params);
if (!$search_count_hash = $Cache->Get($cache_key_search_count)) {
	$APIAccess->AddRequestCountForSearch('search_count', $search_count_params);
}
//------------------------------------------------------------------------------


// Выполняем запрос к API
if ($APIAccess->RunRequests()) {
	// Достаём данные
	if (($categories_list_tmp = $APIAccess->GetRequestResult('categories')) && isset($categories_list_tmp['categories'])) {
		$categories_list = $categories_list_tmp['categories'];
		$Cache->Set($cache_key_categories, $categories_list, 86400);
	}
	if (($offers_tmp = $APIAccess->GetRequestResult('search')) && isset($offers_tmp['offers'])) {
		$offers = $offers_tmp['offers'];
		$total_offers = $offers_tmp['total_found'];
		$Cache->Set($cache_key_search, $offers_tmp, 14400);
	}
	if (($search_count_hash_tmp = $APIAccess->GetRequestResult('search_count')) && isset($search_count_hash_tmp['count'])) {
		$search_count_hash = $search_count_hash_tmp['count'];
		$Cache->Set($cache_key_search_count, $search_count_hash, 14400);
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
//==============================================================================
// Строим структуру с описанием количества товаров в категориях
$categories_total = 0;
foreach ($search_count_hash as $key => $value) {
	if (isset($categories_hash[$key])) {
		$item = $categories_hash[$key];
		$item['count'] = $value;
		$item['link'] = $Path->Search($search_query, 1, $key);
		$item['current'] = $key == $category ? TRUE : FALSE;
		$search_categories_count[] = $item;
		$categories_total += $item['count'];
	}
}
$item = array(
	'title' => $Lang->GetString('All'),
	'count' => $categories_total,
	'current' => $category == 0 ? TRUE : FALSE,
	'link' => $Path->Search($search_query)
);
array_unshift($search_categories_count, $item);
//==============================================================================

/*
print "<!--\n";
print_r($search_categories_count);
print "\n-->\n";
*/

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

// Строим пейджер
$pages = array();
// Общее число страниц
$page_count = ceil($total_offers / $settings['items_per_page']);
// А нужен ли вообще пейджер
if ($page_count > 1) {
	$page_min = $page - 4;
	if ($page_min < 1) $page_min = 1;
	
	$page_max = $page + 4;
	if ($page_max > $page_count) $page_max = $page_count;
	
	if ($page_min > 1) {
		$pages[] = array(
				'page' => '<<',
				'link' => $Path->Search($search_query, 1, $category),
			);
	}
	
	if ($page > 1) {
		$pages[] = array(
				'page' => '<',
				'link' => $Path->Search($search_query, $page - 1, $category),
			);
	}
	
	for ($i = $page_min; $i <= $page_max; $i++) {
		$pages[] = array(
				'page' => $i,
				'link' => $i == $page ? '' : $Path->Search($search_query, $i, $category),
			);
	}

	if ($page < $page_count) {
		$pages[] = array(
				'page' => '>',
				'link' => $Path->Search($search_query, $page + 1, $category),
			);
	}
	
	if ($page_max < $page_count) {
		$pages[] = array(
				'page' => '>>',
				'link' => $Path->Search($search_query, $page_count, $category),
			);
	}
}

// Цепляем шаблон
include_once $Common->GetTemplatePath('search');
